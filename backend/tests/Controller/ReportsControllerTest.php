<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Report;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Tests\TestCase\BaseWebTestCase;

class ReportsControllerTest extends BaseWebTestCase
{
    public function testListReportsWithoutAuthentication(): void
    {
        $this->client->request('GET', '/api/reports');

        $this->assertResponseStatusCodeSame(401);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testListReportsEmpty(): void
    {
        $token = $this->getAuthToken();

        $this->client->request('GET', '/api/reports', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
    }

    public function testListReportsWithExistingReports(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'RPT-TST');

        // Create a test report
        $report = new Report();
        $report->setUser($user);
        $report->setName('Test Report');
        $report->setTemplateKey('maintenance_summary');
        $report->setVehicleId($vehicle->getId());
        $report->setPayload(['test' => 'data']);
        $report->setGeneratedAt(new \DateTime());
        $em->persist($report);
        $em->flush();

        $this->client->request('GET', '/api/reports', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
        $this->assertGreaterThanOrEqual(1, count($responseData), 'Should have at least 1 report');
        
        // Find our created report in the list
        $foundReport = null;
        foreach ($responseData as $r) {
            if ($r['name'] === 'Test Report' && $r['template'] === 'maintenance_summary') {
                $foundReport = $r;
                break;
            }
        }
        $this->assertNotNull($foundReport, 'Created report should be found in list');
        $this->assertEquals('Test Report', $foundReport['name']);
        $this->assertEquals('maintenance_summary', $foundReport['template']);
    }

    public function testCreateReportWithoutAuthentication(): void
    {
        $payload = [
            'name' => 'Test Report',
            'template' => 'maintenance_summary',
            'templateContent' => ['section1' => 'data'],
        ];

        $this->client->request('POST', '/api/reports', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateReportWithoutTemplateContent(): void
    {
        $token = $this->getAuthToken();

        $payload = [
            'name' => 'Invalid Report',
            'template' => 'maintenance_summary',
        ];

        $this->client->request('POST', '/api/reports', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseStatusCodeSame(422);
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertStringContainsString('templateContent', $responseData['error']);
    }

    public function testCreateReportSuccessfully(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $vehicle = $this->createTestVehicle($user, 'RPT-NEW');

        $payload = [
            'name' => 'New Report',
            'template' => 'cost_analysis',
            'vehicleId' => $vehicle->getId(),
            'templateContent' => [
                'title' => 'Cost Analysis',
                'sections' => ['maintenance', 'fuel'],
            ],
        ];

        $this->client->request('POST', '/api/reports', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $responseData);
        $this->assertEquals('New Report', $responseData['name']);
        $this->assertEquals('cost_analysis', $responseData['template']);
        $this->assertEquals($vehicle->getId(), $responseData['vehicleId']);
    }

    public function testDeleteReport(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        
        // Create a report to delete
        $report = new Report();
        $report->setUser($user);
        $report->setName('Report to Delete');
        $report->setTemplateKey('test_template');
        $report->setPayload(['test' => 'data']);
        $report->setGeneratedAt(new \DateTime());
        $em->persist($report);
        $em->flush();

        $reportId = $report->getId();

        $this->client->request('DELETE', '/api/reports/' . $reportId, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();

        // Verify the report was deleted
        $deletedReport = $em->getRepository(Report::class)->find($reportId);
        $this->assertNull($deletedReport);
    }

    public function testGetReportById(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        
        $report = new Report();
        $report->setUser($user);
        $report->setName('Specific Report');
        $report->setTemplateKey('fuel_efficiency');
        $report->setPayload(['data' => 'values']);
        $report->setGeneratedAt(new \DateTime());
        $em->persist($report);
        $em->flush();

        // Get report details - note: there is no GET endpoint, only DELETE
        // So we verify the report was created by listing all reports
        $this->client->request('GET', '/api/reports', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($responseData);
        
        // Find our created report
        $foundReport = null;
        foreach ($responseData as $r) {
            if ($r['id'] === $report->getId()) {
                $foundReport = $r;
                break;
            }
        }
        $this->assertNotNull($foundReport, 'Report should be found in list');
        $this->assertEquals($report->getId(), $foundReport['id']);
        $this->assertEquals('Specific Report', $foundReport['name']);
    }

    /**
     * Test download returns 422 when the persisted report has no templateContent.
     */
    public function testDownloadReportMissingTemplateContentReturns422(): void
    {
        $token = $this->getAuthToken();
        $em = $this->getEntityManager();

        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);

        $report = new Report();
        $report->setUser($user);
        $report->setName('No Template Report');
        $report->setTemplateKey('missing_template');
        $report->setPayload(['someOtherKey' => 'data']);
        $report->setGeneratedAt(new \DateTime());
        $em->persist($report);
        $em->flush();

        $this->client->request('GET', '/api/reports/' . $report->getId() . '/download', [], [], [
            'HTTP_AUTHORIZATION' => $token,
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * Test download returns a generic 500 with no exception detail when the engine throws.
     * Verifies the fix: exception message must not leak to the HTTP response.
     */
    public function testDownloadReportReturnsGenericErrorWhenEngineThrows(): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);

        $report = new Report();
        $report->setUser($user);
        $report->setName('Failing Engine Report');
        $report->setTemplateKey('fail_engine_test');
        // Payload must contain 'layout' or 'dataSources' to trigger the ReportEngine path.
        $report->setPayload([
            'templateContent' => [
                'layout' => ['sections' => []],
                'dataSources' => [],
            ],
        ]);
        $report->setGeneratedAt(new \DateTime());
        $em->persist($report);
        $em->flush();
        $reportId = $report->getId();

        // Replace the ReportEngine with a mock that always throws.
        $mockEngine = $this->createMock(\App\Service\ReportEngine::class);
        $mockEngine->method('generate')->willThrowException(new \RuntimeException('Simulated engine failure - must not appear in response'));
        static::getContainer()->set(\App\Service\ReportEngine::class, $mockEngine);

        $this->client->request('GET', '/api/reports/' . $reportId . '/download', [], [], [
            'HTTP_AUTHORIZATION' => $this->getAuthToken(),
        ]);

        $this->assertResponseStatusCodeSame(500);
        $content = $this->client->getResponse()->getContent();
        $this->assertEquals('Error generating report.', $content, 'Response body must be the generic error message');
        $this->assertStringNotContainsString('Simulated engine failure', $content, 'Exception detail must not leak to client');
    }
}
