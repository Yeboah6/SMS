<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application directs guests to sign in', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
    $this->get('/login')->assertOk();
});
