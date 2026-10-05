<?php

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaApiService
{
    protected $baseUrl = 'https://graph.facebook.com/v20.0';

    /**
     * Obtiene las páginas que administra el usuario usando su User Access Token.
     */
    public function getUserPages(string $userAccessToken)
    {
        $response = Http::get("{$this->baseUrl}/me/accounts", [
            'access_token' => $userAccessToken,
            'fields' => 'id,name,access_token,instagram_business_account,picture'
        ]);

        if ($response->failed()) {
            Log::error('Error al obtener páginas de Meta: ' . $response->body());
            throw new \Exception('No se pudieron obtener las páginas de Facebook.');
        }

        return $response->json('data');
    }

    /**
     * Obtiene la analítica completa de la página (Perfil, Gráficas e 히storial de Posts).
     */
    public function getFullPageAnalytics($pageId, $accessToken)
    {
        // 1. Obtener Info General del Perfil (Seguidores, Likes, Foto)
        $profileResponse = Http::get("{$this->baseUrl}/{$pageId}", [
            'fields' => 'name,followers_count,fan_count,picture{url}',
            'access_token' => $accessToken
        ]);
        $profile = $profileResponse->json();

        // 2. Obtener Métricas Globales (Alcance e Interacciones de los últimos 7 días)
        $insightsResponse = Http::get("{$this->baseUrl}/{$pageId}/insights", [
            'metric' => 'page_impressions_unique,page_post_engagements',
            'period' => 'day',
            'date_preset' => 'last_7d',
            'access_token' => $accessToken
        ]);
        $insightsRaw = $insightsResponse->json()['data'] ?? [];

        // 3. Obtener las Publicaciones recientes
        $postsResponse = Http::get("{$this->baseUrl}/{$pageId}/posts", [
            'fields' => 'message,created_time,permalink_url,attachments,comments.summary(total_count),shares',
            'limit' => 15,
            'access_token' => $accessToken
        ]);
        $posts = $postsResponse->json()['data'] ?? [];

        // 4. Estandarizar la respuesta para el Frontend
        return [
            'account_info' => [
                'name' => $profile['name'] ?? 'Página Desconocida',
                'avatar' => $profile['picture']['data']['url'] ?? null,
                'followers' => $profile['followers_count'] ?? 0,
                'following' => 0, 
                'likes' => $profile['fan_count'] ?? 0,
            ],
            'metrics_summary' => $this->formatInsightsForChart($insightsRaw),
            'posts' => $posts
        ];
    }

    /**
     * Formatea los datos crudos de Meta Insights para usarlos directamente en Recharts de manera segura.
     */
    private function formatInsightsForChart($insightsRaw)
    {
        $chartData = [];
        $totals = ['reach' => 0, 'engagement' => 0];

        // Extracción segura usando validación de nulos (Evita errores fatales si la API no devuelve datos)
        $reachItem = collect($insightsRaw)->firstWhere('name', 'page_impressions_unique');
        $reachData = $reachItem['values'] ?? [];

        $engagementItem = collect($insightsRaw)->firstWhere('name', 'page_post_engagements');
        $engagementData = $engagementItem['values'] ?? [];

        foreach ($reachData as $index => $reach) {
            $date = \Carbon\Carbon::parse($reach['end_time'])->format('d/m');
            $reachValue = $reach['value'] ?? 0;
            $engagementValue = $engagementData[$index]['value'] ?? 0;

            $totals['reach'] += $reachValue;
            $totals['engagement'] += $engagementValue;

            $chartData[] = [
                'date' => $date,
                'alcance' => $reachValue,
                'interacciones' => $engagementValue
            ];
        }

        return [
            'totals' => $totals,
            'chart_data' => $chartData
        ];
    }

    /**
     * Obtiene datos demográficos y geográficos de la audiencia de la página.
     */
    public function getPageAudienceDemographics($pageId, $accessToken)
    {
        $response = Http::get("{$this->baseUrl}/{$pageId}/insights", [
            'metric' => 'page_fans_gender_age,page_fans_city,page_fans_country',
            'period' => 'lifetime',
            'access_token' => $accessToken
        ]);

        $data = $response->json()['data'] ?? [];

        // Procesar Género y Edad (viene como clave ej: "F.25-34" => cantidad)
        $genderAgeItem = collect($data)->firstWhere('name', 'page_fans_gender_age');
        $genderAgeRaw = $genderAgeItem['values'][0]['value'] ?? [];

        $demographics = [
            'gender_age' => $genderAgeRaw,
            'cities' => collect($data)->firstWhere('name', 'page_fans_city')['values'][0]['value'] ?? [],
            'countries' => collect($data)->firstWhere('name', 'page_fans_country')['values'][0]['value'] ?? []
        ];

        return $demographics;
    }

    /**
     * Obtiene el rendimiento detallado de las publicaciones (Alcance individual, clics, reacciones)
     */
    public function getDetailedPostsPerformance($pageId, $accessToken)
    {
        // Solicitamos los posts con campos más profundos de métricas e interacciones
        $response = Http::get("{$this->baseUrl}/{$pageId}/posts", [
            'fields' => 'id,message,created_time,permalink_url,attachments{media,type},comments.summary(total_count),shares,reactions.summary(total_count),insights.metric(post_impressions, post_engaged_users)',
            'limit' => 10,
            'access_token' => $accessToken
        ]);

        $posts = $response->json()['data'] ?? [];

        return collect($posts)->map(function ($post) {
            // Extraer insights individuales del post si vienen anidados
            $insights = $post['insights']['data'] ?? [];
            $impressions = collect($insights)->firstWhere('name', 'post_impressions')['values'][0]['value'] ?? 0;
            $engagedUsers = collect($insights)->firstWhere('name', 'post_engaged_users')['values'][0]['value'] ?? 0;

            return [
                'id' => $post['id'] ?? null,
                'message' => $post['message'] ?? 'Sin texto',
                'created_time' => $post['created_time'] ?? null,
                'permalink_url' => $post['permalink_url'] ?? '#',
                'type' => $post['attachments']['data'][0]['type'] ?? 'link',
                'media_url' => $post['attachments']['data'][0]['media']['image']['src'] ?? null,
                'metrics' => [
                    'likes' => $post['reactions']['summary']['total_count'] ?? 0,
                    'comments' => $post['comments']['summary']['total_count'] ?? 0,
                    'shares' => $post['shares']['count'] ?? 0,
                    'impressions' => $impressions,
                    'engaged_users' => $engagedUsers,
                ]
            ];
        });
    }
}