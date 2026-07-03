<?php
$client = new \GuzzleHttp\Client();
try {
    $response = $client->post('http://localhost:8000/api/administrasi/profil-web', [
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer 1|xxx' // Actually, let's bypass auth in testing route.
        ],
        'json' => [
            'tipe' => 'TKB',
            'nama' => 'TK B',
            'visi' => '',
            'misi' => [],
            'sejarah' => '',
            'program_unggulan' => []
        ]
    ]);
    echo $response->getBody();
} catch (\GuzzleHttp\Exception\ClientException $e) {
    echo $e->getResponse()->getBody();
}
