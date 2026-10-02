<?php
/**
 * Test Runner Sederhana untuk Pemandian Patemon
 * Menjalankan suite pengujian logika & database secara otomatis.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../dist/app/config.php';

class TestRunner {
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function assert(bool $condition, string $testName, string $failMessage = ''): void {
        if ($condition) {
            $this->passed++;
            echo "  \033[32m✔ PASS\033[0m: {$testName}\n";
        } else {
            $this->failed++;
            $msg = $failMessage ?: "Kondisi tidak terpenuhi";
            $this->errors[] = "{$testName}: {$msg}";
            echo "  \033[31m✘ FAIL\033[0m: {$testName} - {$msg}\n";
        }
    }

    public function summarize(): int {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "HASIL PENGUJIAN: {$this->passed} Passed, {$this->failed} Failed\n";
        echo str_repeat('=', 60) . "\n";
        if ($this->failed > 0) {
            echo "\033[31mDetail Kegagalan:\033[0m\n";
            foreach ($this->errors as $err) {
                echo "  - {$err}\n";
            }
            return 1;
        }
        echo "\033[32mSemua pengujian berhasil tanpa kendala!\033[0m\n";
        return 0;
    }
}
