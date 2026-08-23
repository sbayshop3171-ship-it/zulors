<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected static ?string $isolatedSqliteDatabasePath = null;

    public function createApplication()
    {
        $this->configureIsolatedSqliteDatabase();

        return parent::createApplication();
    }

    protected function configureIsolatedSqliteDatabase(): void
    {
        if (static::$isolatedSqliteDatabasePath === null) {
            $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zulors-phpunit';

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            static::$isolatedSqliteDatabasePath = $directory
                . DIRECTORY_SEPARATOR
                . 'testing-'
                . getmypid()
                . '-'
                . bin2hex(random_bytes(6))
                . '.sqlite';

            if (file_exists(static::$isolatedSqliteDatabasePath)) {
                @unlink(static::$isolatedSqliteDatabasePath);
            }

            touch(static::$isolatedSqliteDatabasePath);

            register_shutdown_function(static function (): void {
                if (
                    static::$isolatedSqliteDatabasePath !== null
                    && file_exists(static::$isolatedSqliteDatabasePath)
                ) {
                    @unlink(static::$isolatedSqliteDatabasePath);
                }
            });
        }

        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', static::$isolatedSqliteDatabasePath);
    }

    protected function setEnvironmentValue(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
