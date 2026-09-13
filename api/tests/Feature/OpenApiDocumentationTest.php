<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenApiDocumentationTest extends TestCase
{
    public function test_the_documentation_ui_is_exposed_at_the_existing_api_path(): void
    {
        $this->get('/api/v1/docs')
            ->assertOk()
            ->assertSee('Scalar.createApiReference', escape: false)
            ->assertSee('credentials', escape: false);
    }

    public function test_the_openapi_document_matches_the_laravel_routes(): void
    {
        $response = $this->getJson('/api/v1/docs/openapi.json')->assertOk();
        $specification = $response->json();

        $this->assertSame('3.1.0', $specification['openapi']);
        $this->assertSame('Job Tracker API', $specification['info']['title']);
        $this->assertSame('v1', $specification['info']['version']);

        $expectedOperations = [
            '/sanctum/csrf-cookie' => ['get'],
            '/api/v1/health' => ['get'],
            '/api/v1/auth/login' => ['post'],
            '/api/v1/auth/logout' => ['post'],
            '/api/v1/auth/me' => ['get'],
            '/api/v1/users' => ['get', 'post'],
            '/api/v1/users/{user}' => ['get', 'put', 'patch', 'delete'],
            '/api/v1/companies' => ['get', 'post'],
            '/api/v1/companies/{company}' => ['get', 'put', 'patch', 'delete'],
            '/api/v1/job-applications' => ['get', 'post'],
            '/api/v1/job-applications/{jobApplication}' => ['get', 'put', 'patch', 'delete'],
            '/api/v1/job-applications/{jobApplication}/activities' => ['get', 'post'],
            '/api/v1/job-applications/{jobApplication}/interviews' => ['get', 'post'],
            '/api/v1/job-applications/{jobApplication}/interviews/{interview}' => [
                'put',
                'patch',
                'delete',
            ],
            '/api/v1/dashboard' => ['get'],
        ];

        foreach ($expectedOperations as $path => $methods) {
            $this->assertArrayHasKey($path, $specification['paths']);

            foreach ($methods as $method) {
                $this->assertArrayHasKey($method, $specification['paths'][$path]);
            }
        }

        $this->assertArrayNotHasKey('/api/v1/docs', $specification['paths']);
        $this->assertArrayNotHasKey('/api/v1/docs/openapi.json', $specification['paths']);

        $this->assertArrayHasKey('204', $specification['paths']['/sanctum/csrf-cookie']['get']['responses']);
        $this->assertArrayHasKey('200', $specification['paths']['/api/v1/health']['get']['responses']);
        $this->assertArrayHasKey('200', $specification['paths']['/api/v1/auth/login']['post']['responses']);
        $this->assertArrayHasKey('201', $specification['paths']['/api/v1/users']['post']['responses']);
        $this->assertArrayHasKey('200', $specification['paths']['/api/v1/users/{user}']['delete']['responses']);
        $this->assertArrayHasKey('201', $specification['paths']['/api/v1/companies']['post']['responses']);
        $this->assertArrayHasKey('201', $specification['paths']['/api/v1/job-applications']['post']['responses']);
        $this->assertArrayHasKey(
            '201',
            $specification['paths']['/api/v1/job-applications/{jobApplication}/interviews']['post']['responses'],
        );
        $this->assertArrayHasKey(
            '201',
            $specification['paths']['/api/v1/job-applications/{jobApplication}/activities']['post']['responses'],
        );
        $this->assertArrayHasKey('200', $specification['paths']['/api/v1/dashboard']['get']['responses']);

        $healthResponse = json_encode(
            $specification['paths']['/api/v1/health']['get']['responses']['200'],
            JSON_THROW_ON_ERROR,
        );
        $deleteResponse = json_encode(
            $specification['paths']['/api/v1/users/{user}']['delete']['responses']['200'],
            JSON_THROW_ON_ERROR,
        );
        $loginResourceReference = $specification['paths']['/api/v1/auth/login']['post']
            ['responses']['200']['content']['application/json']['schema']['properties']['data']['$ref'];
        $loginResourceName = basename($loginResourceReference);
        $loginResource = $specification['components']['schemas'][$loginResourceName];

        $this->assertStringContainsString('status', $healthResponse);
        $this->assertStringContainsString('timestamp', $healthResponse);
        $this->assertEqualsCanonicalizing(
            ['id', 'email', 'role'],
            array_keys($loginResource['properties']),
        );
        $this->assertStringContainsString('deleted', $deleteResponse);
    }

    public function test_filters_pagination_errors_and_sanctum_security_are_documented(): void
    {
        $specification = $this->getJson('/api/v1/docs/openapi.json')->json();
        $listUsers = $specification['paths']['/api/v1/users']['get'];

        $queryParameters = collect($listUsers['parameters'])
            ->where('in', 'query')
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing(
            ['page', 'per_page', 'search', 'gender'],
            $queryParameters,
        );

        $successfulListSchema = $listUsers['responses']['200']['content']['application/json']['schema'];
        $serializedSchema = json_encode($successfulListSchema, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('data', $serializedSchema);
        $this->assertStringContainsString('links', $serializedSchema);
        $this->assertStringContainsString('meta', $serializedSchema);

        $securitySchemes = $specification['components']['securitySchemes'];
        $this->assertSame('cookie', $securitySchemes['sanctumSession']['in']);
        $this->assertSame('XSRF-TOKEN', $securitySchemes['xsrfCookie']['name']);
        $this->assertSame('X-XSRF-TOKEN', $securitySchemes['csrfHeader']['name']);

        $this->assertSame([], $specification['paths']['/api/v1/health']['get']['security']);
        $this->assertSame([], $specification['paths']['/sanctum/csrf-cookie']['get']['security']);
        $this->assertSame(
            ['sanctumSession' => []],
            $specification['paths']['/api/v1/auth/me']['get']['security'][0],
        );
        $this->assertSame(
            ['xsrfCookie' => [], 'csrfHeader' => []],
            $specification['paths']['/api/v1/auth/login']['post']['security'][0],
        );

        $this->assertArrayHasKey('ApiError', $specification['components']['schemas']);
        $this->assertArrayHasKey('401', $specification['paths']['/api/v1/auth/login']['post']['responses']);
        $this->assertArrayHasKey('422', $specification['paths']['/api/v1/users']['post']['responses']);
        $this->assertArrayHasKey('403', $specification['paths']['/api/v1/users/{user}']['delete']['responses']);
        $this->assertArrayHasKey('404', $specification['paths']['/api/v1/users/{user}']['get']['responses']);
    }

    public function test_job_tracker_contracts_ownership_and_security_are_documented(): void
    {
        $specification = $this->getJson('/api/v1/docs/openapi.json')->json();
        $paths = $specification['paths'];

        $companyParameters = collect($paths['/api/v1/companies']['get']['parameters'])
            ->where('in', 'query')
            ->pluck('name')
            ->all();
        $applicationParameters = collect($paths['/api/v1/job-applications']['get']['parameters'])
            ->where('in', 'query')
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing(
            ['page', 'per_page', 'search'],
            $companyParameters,
        );
        $this->assertEqualsCanonicalizing(
            ['page', 'per_page', 'search', 'status', 'work_mode', 'company_id', 'sort', 'direction'],
            $applicationParameters,
        );

        $applicationRequest = json_encode($this->resolveSchemaReference(
            $specification,
            $paths['/api/v1/job-applications']['post']
                ['requestBody']['content']['application/json']['schema'],
        ), JSON_THROW_ON_ERROR);
        $interviewRequest = json_encode($this->resolveSchemaReference(
            $specification,
            $paths['/api/v1/job-applications/{jobApplication}/interviews']['post']
                ['requestBody']['content']['application/json']['schema'],
        ), JSON_THROW_ON_ERROR);
        $activityCommentRequest = json_encode($this->resolveSchemaReference(
            $specification,
            $paths['/api/v1/job-applications/{jobApplication}/activities']['post']
                ['requestBody']['content']['application/json']['schema'],
        ), JSON_THROW_ON_ERROR);
        $dashboardEnvelope = $this->resolveSchemaReference(
            $specification,
            $paths['/api/v1/dashboard']['get']['responses']['200']
                ['content']['application/json']['schema'],
        );
        $dashboardResponse = json_encode($this->resolveSchemaReference(
            $specification,
            $dashboardEnvelope['properties']['data'],
        ), JSON_THROW_ON_ERROR);

        foreach (['company_id', 'position', 'status', 'work_mode', 'salary_min', 'currency'] as $field) {
            $this->assertStringContainsString($field, $applicationRequest);
        }

        foreach (['type', 'scheduled_at', 'contact_email', 'outcome'] as $field) {
            $this->assertStringContainsString($field, $interviewRequest);
        }

        $this->assertStringContainsString('comment', $activityCommentRequest);
        $this->assertStringNotContainsString('actor_auth_user_id', $activityCommentRequest);

        foreach (
            ['total_applications', 'applications_by_status', 'recent_applications', 'upcoming_interviews']
            as $field
        ) {
            $this->assertStringContainsString($field, $dashboardResponse);
        }

        $this->assertSame(
            ['Companies'],
            $paths['/api/v1/companies']['get']['tags'],
        );
        $this->assertSame(
            ['Job Applications'],
            $paths['/api/v1/job-applications']['get']['tags'],
        );
        $this->assertSame(
            ['Interviews'],
            $paths['/api/v1/job-applications/{jobApplication}/interviews']['get']['tags'],
        );
        $this->assertSame(
            ['Application Activities'],
            $paths['/api/v1/job-applications/{jobApplication}/activities']['get']['tags'],
        );
        $this->assertSame(
            ['Dashboard'],
            $paths['/api/v1/dashboard']['get']['tags'],
        );

        $this->assertSame(
            ['sanctumSession' => []],
            $paths['/api/v1/dashboard']['get']['security'][0],
        );
        $this->assertSame(
            ['sanctumSession' => [], 'xsrfCookie' => [], 'csrfHeader' => []],
            $paths['/api/v1/job-applications']['post']['security'][0],
        );

        $this->assertArrayHasKey('422', $paths['/api/v1/job-applications']['get']['responses']);
        $this->assertArrayHasKey('422', $paths['/api/v1/job-applications']['post']['responses']);
        $this->assertArrayHasKey('404', $paths['/api/v1/job-applications/{jobApplication}']['get']['responses']);
        $this->assertArrayHasKey(
            '404',
            $paths['/api/v1/job-applications/{jobApplication}/interviews/{interview}']['patch']['responses'],
        );
        $this->assertArrayHasKey(
            '422',
            $paths['/api/v1/job-applications/{jobApplication}/interviews']['post']['responses'],
        );
        $this->assertArrayHasKey('409', $paths['/api/v1/companies/{company}']['delete']['responses']);

        $this->assertStringContainsString(
            'private to the authenticated account',
            $specification['info']['description'],
        );
        $this->assertStringContainsString(
            'owned job application',
            strtolower((string) $paths['/api/v1/job-applications/{jobApplication}']['get']['summary']),
        );
    }

    /**
     * @param  array<string, mixed>  $specification
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function resolveSchemaReference(array $specification, array $schema): array
    {
        if (! isset($schema['$ref'])) {
            return $schema;
        }

        return $specification['components']['schemas'][basename($schema['$ref'])];
    }
}
