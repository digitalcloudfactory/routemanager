<?php
// session_handler.php

class MySqlSessionHandler implements SessionHandlerInterface {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function open($savePath, $sessionName): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read($id): string {
        $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $row['data'] ?? '';
        }
        return '';
    }

    public function write($id, $data): bool {
        $access = time();
        $stmt = $this->pdo->prepare("REPLACE INTO sessions (id, access, data) VALUES (:id, :access, :data)");
        return $stmt->execute([':id' => $id, ':access' => $access, ':data' => $data]);
    }

    public function destroy($id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function gc($maxlifetime): int|false {
        $old = time() - $maxlifetime;
        $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE access < :old");
        if ($stmt->execute([':old' => $old])) {
            return $stmt->rowCount();
        }
        return false;
    }
}