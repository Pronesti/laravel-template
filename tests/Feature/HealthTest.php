<?php

declare(strict_types=1);

it('exposes a health endpoint', function (): void {
    $this->get('/up')->assertOk();
});

it('returns 404 at the site root', function (): void {
    $this->get('/')->assertNotFound();
});
