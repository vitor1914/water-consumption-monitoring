<?php
declare(strict_types=1);

final class Database
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível criar o diretório do banco de dados.');
        }

        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->initialize();
    }

    private function initialize(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS readings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reading_date TEXT NOT NULL,
                sector TEXT NOT NULL,
                water_produced REAL NOT NULL CHECK(water_produced >= 0),
                water_billed REAL NOT NULL CHECK(water_billed >= 0),
                energy_kwh REAL NOT NULL CHECK(energy_kwh >= 0),
                notes TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CHECK(water_billed <= water_produced)
            )
        SQL);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM readings')->fetchColumn();
        if ($count === 0) {
            $insert = $this->pdo->prepare('INSERT INTO readings (reading_date, sector, water_produced, water_billed, energy_kwh, notes) VALUES (?, ?, ?, ?, ?, ?)');
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
            foreach ($samples as $sample) {
                $insert->execute($sample);
            }
        }
    }

    public function all(string $from = '', string $to = '', string $sector = ''): array
    {
        $sql = 'SELECT * FROM readings WHERE 1=1';
        $params = [];
        if ($from !== '') { $sql .= ' AND reading_date >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND reading_date <= ?'; $params[] = $to; }
        if ($sector !== '') { $sql .= ' AND sector = ?'; $params[] = $sector; }
        $sql .= ' ORDER BY reading_date DESC, id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO readings (reading_date, sector, water_produced, water_billed, energy_kwh, notes) VALUES (:date, :sector, :produced, :billed, :energy, :notes)');
        $stmt->execute([
            ':date' => $data['reading_date'], ':sector' => $data['sector'],
            ':produced' => $data['water_produced'], ':billed' => $data['water_billed'],
            ':energy' => $data['energy_kwh'], ':notes' => $data['notes'],
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM readings WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function sectors(): array
    {
        return $this->pdo->query('SELECT DISTINCT sector FROM readings ORDER BY sector')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function pdo(): PDO { return $this->pdo; }
}
