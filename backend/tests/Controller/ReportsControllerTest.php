<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Report;
use App\Entity\User;
use App\Tests\TestCase\BaseWebTestCase;

class ReportsControllerTest extends BaseWebTestCase
{
    public function testListAndCreateReport(): void
    {
        $this->client->request('GET', '/api/reports', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
        ]);
        $this->assertResponseIsSuccessful();

        $payload = [
            'name' => 'New Report',
            'template' => 'cost_analysis',
            'templateContent' => [
                'title' => 'Cost Analysis',
                'sections' => ['maintenance', 'fuel'],
            ],
        ];

        $this->client->request('POST', '/api/reports', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseStatusCodeSame(201);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $responseData);
    }

    public function testCreateReportWithoutTemplateContentReturns422(): void
    {
        $this->client->request('POST', '/api/reports', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Invalid Report',
            'template' => 'maintenance_summary',
        ]));

        $this->assertResponseStatusCodeSame(422);
    }
}
