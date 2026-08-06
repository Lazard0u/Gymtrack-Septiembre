<?php

declare(strict_types=1);

final class PublicGymController
{
    public function index(): void
    {
        $gyms = (new Gimnasio())->listarPublicados();
        $cities = [];
        $categories = [];
        foreach ($gyms as $gym) {
            $cities[$gym['ciudad']] = true;
            foreach ($gym['categorias'] as $category) {
                $categories[$category] = true;
            }
        }

        $this->respond(200, [
            'error' => false,
            'gimnasios' => $gyms,
            'meta' => [
                'total' => count($gyms),
                'demo_data' => count(array_filter($gyms, fn (array $gym): bool => $gym['is_demo'])) > 0,
                'filtros' => [
                    'ciudades' => array_keys($cities),
                    'categorias' => array_keys($categories),
                ],
            ],
        ]);
    }

    private function respond(int $status, array $body): void
    {
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
