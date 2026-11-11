<?php
declare(strict_types=1);

final class Database
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        $directory = dirname($path);

        if (
            !is_dir($directory) &&
            !mkdir($directory, 0775, true) &&
            !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Não foi possível criar o diretório do banco de dados.'
            );
        }

        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');

        $this->initialize();
    }

    /**
     * Inicializa a estrutura do banco de dados.
     */
    private function initialize(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS readings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reading_date TEXT NOT NULL,
                sector TEXT NOT NULL,
                water_produced REAL NOT NULL
                    CHECK(water_produced >= 0),
                water_billed REAL NOT NULL
                    CHECK(water_billed >= 0),
                energy_kwh REAL NOT NULL
                    CHECK(energy_kwh >= 0),
                notes TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CHECK(water_billed <= water_produced)
            )
        SQL);

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS app_metadata (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )
        SQL);

        // Índices para otimizar consultas.
        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_readings_date
             ON readings(reading_date)'
        );

        $this->pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_readings_sector_date
             ON readings(sector, reading_date)'
        );

        $this->seedSampleData();
    }

    /**
     * Insere dados de demonstração somente quando necessário.
     */
    private function seedSampleData(): void
    {
        $count = (int) $this->pdo->query(
            'SELECT COUNT(*) FROM readings'
        )->fetchColumn();

        $check = $this->pdo->prepare(
            'SELECT value FROM app_metadata WHERE key = ?'
        );

        $check->execute(['sample_data_seeded']);

        $seeded = $check->fetchColumn();

        // Banco com registros existentes: preserva os dados.
        if ($count > 0) {
            $this->markSampleDataSeeded();
            return;
        }

        // Banco anteriormente inicializado e intencionalmente vazio.
        if ($seeded === '1') {
            return;
        }

        $samples = [
            ['2026-09-01', 'Setor Norte', 12000, 10100, 850, 'Medição de referência'],
            ['2026-09-02', 'Setor Sul', 9800, 8100, 730, 'Operação normal'],
            ['2026-09-03', 'Setor Central', 15400, 13300, 1120, ''],
            ['2026-09-04', 'Setor Norte', 12500, 10300, 890, 'Verificar variação'],
            ['2026-09-05', 'Setor Sul', 10200, 8500, 745, ''],
            ['2026-09-06', 'Setor Central', 14900, 12800, 1090, ''],
            ['2026-09-07', 'Setor Norte', 11800, 9900, 820, ''],
            ['2026-09-08', 'Setor Sul', 10400, 8600, 760, ''],
            ['2026-09-09', 'Setor Central', 15800, 13400, 1150, 'Pico de demanda'],
            ['2026-09-10', 'Setor Norte', 12100, 10200, 840, ''],
        ];

        $this->pdo->beginTransaction();

        try {
            $insert = $this->pdo->prepare(
                'INSERT INTO readings (
                    reading_date,
                    sector,
                    water_produced,
                    water_billed,
                    energy_kwh,
                    notes
                ) VALUES (?, ?, ?, ?, ?, ?)'
            );

            foreach ($samples as $sample) {
                $insert->execute($sample);
            }

            $this->markSampleDataSeeded();

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Registra a inicialização dos dados de demonstração.
     */
    private function markSampleDataSeeded(): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO app_metadata (key, value)
             VALUES (?, ?)
             ON CONFLICT(key)
             DO UPDATE SET value = excluded.value'
        );

        $stmt->execute(['sample_data_seeded', '1']);
    }

    /**
     * Valida uma data no formato YYYY-MM-DD.
     */
    private function validateDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        $errors = DateTimeImmutable::getLastErrors();

        return $parsed !== false
            && $parsed->format('Y-m-d') === $date
            && ($errors === false ||
                ($errors['warning_count'] === 0 &&
                 $errors['error_count'] === 0));
    }

    /**
     * Retorna medições com filtros opcionais.
     */
    public function all(
        string $from = '',
        string $to = '',
        string $sector = ''
    ): array {
        $sql = 'SELECT * FROM readings WHERE 1=1';

        $params = [];

        if ($from !== '') {
            $sql .= ' AND reading_date >= ?';
            $params[] = $from;
        }

        if ($to !== '') {
            $sql .= ' AND reading_date <= ?';
            $params[] = $to;
        }

        if ($sector !== '') {
            $sql .= ' AND sector = ?';
            $params[] = $sector;
        }

        $sql .= ' ORDER BY reading_date DESC, id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Cadastra uma nova medição.
     */
    public function create(array $data): void
    {
        $date = trim((string) ($data['reading_date'] ?? ''));
        $sector = trim((string) ($data['sector'] ?? ''));

        $produced = $data['water_produced'] ?? null;
        $billed = $data['water_billed'] ?? null;
        $energy = $data['energy_kwh'] ?? null;

        $notes = trim((string) ($data['notes'] ?? ''));

        if (!$this->validateDate($date)) {
            throw new InvalidArgumentException(
                'Data inválida. Utilize o formato YYYY-MM-DD.'
            );
        }

        if ($sector === '' || strlen($sector) > 100) {
            throw new InvalidArgumentException(
                'Informe um setor válido com até 100 caracteres.'
            );
        }

        foreach ([$produced, $billed, $energy] as $value) {
            if (!is_numeric($value) || !is_finite((float) $value)) {
                throw new InvalidArgumentException(
                    'Os valores numéricos informados são inválidos.'
                );
            }

            if ((float) $value < 0) {
                throw new InvalidArgumentException(
                    'Os valores não podem ser negativos.'
                );
            }
        }

        $produced = (float) $produced;
        $billed = (float) $billed;
        $energy = (float) $energy;

        if ($billed > $produced) {
            throw new InvalidArgumentException(
                'O volume faturado não pode superar o volume produzido.'
            );
        }

        if (strlen($notes) > 1000) {
            throw new InvalidArgumentException(
                'As observações devem ter até 1000 caracteres.'
            );
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO readings (
                reading_date,
                sector,
                water_produced,
                water_billed,
                energy_kwh,
                notes
            ) VALUES (
                :date,
                :sector,
                :produced,
                :billed,
                :energy,
                :notes
            )'
        );

        $stmt->execute([
            ':date' => $date,
            ':sector' => $sector,
            ':produced' => $produced,
            ':billed' => $billed,
            ':energy' => $energy,
            ':notes' => $notes,
        ]);
    }

    /**
     * Exclui uma medição pelo identificador.
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM readings WHERE id = ?'
        );

        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Retorna os setores cadastrados.
     */
    public function sectors(): array
    {
        return $this->pdo->query(
            'SELECT DISTINCT sector
             FROM readings
             ORDER BY sector'
        )->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Disponibiliza a conexão PDO.
     */
    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
