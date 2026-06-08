<?php

test('the api returns a successful response', function () {
    $response = $this->getJson('/api/v1');

    $response->assertOk()
        ->assertJson(['test' => 'test']);
});
