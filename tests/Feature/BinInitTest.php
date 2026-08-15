<?php

declare(strict_types=1);

it('rewrites project metadata and removes itself', function (): void {
    $sandbox = sys_get_temp_dir().'/tpl-'.bin2hex(random_bytes(4));

    try {
        mkdir($sandbox.'/bin', 0o755, true);
        mkdir($sandbox.'/docs/superpowers', 0o755, true);
        copy(base_path('bin/init'), $sandbox.'/bin/init');
        chmod($sandbox.'/bin/init', 0o755);
        file_put_contents($sandbox.'/composer.json', json_encode([
            'name' => 'laravel/laravel',
            'description' => 'The skeleton application for the Laravel framework.',
        ], JSON_PRETTY_PRINT));
        file_put_contents($sandbox.'/.env.example', "APP_NAME=Laravel\n");
        file_put_contents($sandbox.'/README.md', "# Laravel API Template\n");
        file_put_contents($sandbox.'/docs/superpowers/spec.md', 'internal');

        exec(sprintf('cd %s && SKIP_INSTALL=1 ./bin/init acme/billing-api "Billing API" 2>&1', escapeshellarg($sandbox)), $output, $status);

        expect($status)->toBe(0)
            ->and(file_get_contents($sandbox.'/composer.json'))->toContain('acme/billing-api')
            ->and(file_get_contents($sandbox.'/composer.json'))->toContain('"license": "proprietary"')
            ->and(file_get_contents($sandbox.'/.env.example'))->toContain('APP_NAME="Billing API"')
            ->and(file_get_contents($sandbox.'/README.md'))->toContain('# Billing API')
            ->and(file_exists($sandbox.'/bin/init'))->toBeFalse()
            ->and(is_dir($sandbox.'/docs/superpowers'))->toBeFalse();
    } finally {
        exec(sprintf('rm -rf %s', escapeshellarg($sandbox)));
    }
});

it('handles app names containing regex and quote metacharacters', function (): void {
    $sandbox = sys_get_temp_dir().'/tpl-'.bin2hex(random_bytes(4));

    try {
        mkdir($sandbox.'/bin', 0o755, true);
        copy(base_path('bin/init'), $sandbox.'/bin/init');
        chmod($sandbox.'/bin/init', 0o755);
        file_put_contents($sandbox.'/composer.json', json_encode(['name' => 'laravel/laravel'], JSON_PRETTY_PRINT));
        file_put_contents($sandbox.'/.env.example', "APP_NAME=Laravel\n");
        // .env too: this branch of the loop was previously never exercised.
        file_put_contents($sandbox.'/.env', "APP_NAME=Laravel\n");
        file_put_contents($sandbox.'/README.md', "# Laravel API Template\n");

        exec(sprintf(
            'cd %s && SKIP_INSTALL=1 ./bin/init acme/billing-api %s 2>&1',
            escapeshellarg($sandbox),
            escapeshellarg('Acme $1 "Co" \\ Ltd')
        ), $output, $status);

        // $1 must survive literally: it is a preg backreference in a replacement string.
        // The quotes and backslash must be escaped for .env's double-quoted syntax.
        $expectedEnv = 'APP_NAME="Acme $1 \"Co\" \\\\ Ltd"'."\n";

        expect($status)->toBe(0)
            ->and(file_get_contents($sandbox.'/.env.example'))->toBe($expectedEnv)
            ->and(file_get_contents($sandbox.'/.env'))->toBe($expectedEnv)
            ->and(file_get_contents($sandbox.'/README.md'))->toBe('# Acme $1 "Co" \\ Ltd'."\n");
    } finally {
        exec(sprintf('rm -rf %s', escapeshellarg($sandbox)));
    }
});

it('removes the template-only documentation from a generated project', function (): void {
    $sandbox = sys_get_temp_dir().'/tpl-'.bin2hex(random_bytes(4));

    try {
        mkdir($sandbox.'/bin', 0o755, true);
        mkdir($sandbox.'/docs/superpowers', 0o755, true);
        copy(base_path('bin/init'), $sandbox.'/bin/init');
        chmod($sandbox.'/bin/init', 0o755);
        file_put_contents($sandbox.'/composer.json', json_encode(['name' => 'laravel/laravel'], JSON_PRETTY_PRINT));
        file_put_contents($sandbox.'/.env.example', "APP_NAME=Laravel\n");
        file_put_contents($sandbox.'/TEMPLATE.md', 'template-only prose');
        file_put_contents($sandbox.'/docs/superpowers/spec.md', 'internal');
        file_put_contents(
            $sandbox.'/README.md',
            "# Laravel API Template\n\n> Creating a new project from this template? Start with [TEMPLATE.md](TEMPLATE.md). `bin/init` removes both that file and this line.\n\n## What this is\n"
        );

        exec(sprintf('cd %s && SKIP_INSTALL=1 ./bin/init acme/billing-api "Billing API" 2>&1', escapeshellarg($sandbox)), $output, $status);

        $readme = (string) file_get_contents($sandbox.'/README.md');

        expect($status)->toBe(0)
            ->and(file_exists($sandbox.'/TEMPLATE.md'))->toBeFalse()
            ->and(is_dir($sandbox.'/docs/superpowers'))->toBeFalse()
            ->and($readme)->toContain('# Billing API')
            // The pointer line would dangle once TEMPLATE.md is gone.
            ->and($readme)->not->toContain('TEMPLATE.md');
    } finally {
        exec(sprintf('rm -rf %s', escapeshellarg($sandbox)));
    }
});

it('exits non-zero when arguments are missing and stdin is not a tty', function (): void {
    exec(sprintf('cd %s && ./bin/init < /dev/null 2>&1', escapeshellarg(base_path())), $output, $status);

    expect($status)->not->toBe(0)
        ->and(file_exists(base_path('bin/init')))->toBeTrue();
});
