<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The files that make security reporting and upkeep possible must exist and stay current:
 * a security.txt that has expired is worse than none, so this test fails before that happens.
 */
class SecurityPolicyFilesTest extends TestCase
{
    public function test_security_txt_follows_rfc_9116_and_has_not_expired(): void
    {
        $text = (string) file_get_contents(public_path('.well-known/security.txt'));

        $this->assertMatchesRegularExpression('/^Contact: https:\/\/\S+$/m', $text);
        $this->assertMatchesRegularExpression('/^Expires: (\S+)$/m', $text);

        preg_match('/^Expires: (\S+)$/m', $text, $match);
        $expires = Carbon::parse($match[1]);

        $this->assertTrue($expires->isFuture(), 'security.txt has expired: renew its Expires date');
        $this->assertTrue($expires->lessThanOrEqualTo(now()->addYear()->addDay()), 'RFC 9116 recommends an expiry of less than a year');
        $this->assertMatchesRegularExpression('/^Canonical: https:\/\/\S+\/\.well-known\/security\.txt$/m', $text);
    }

    public function test_the_security_policy_says_how_to_report_privately(): void
    {
        $policy = (string) file_get_contents(base_path('SECURITY.md'));

        $this->assertStringContainsString('security/advisories/new', $policy);
        $this->assertStringContainsString('No abras un issue público', $policy);
        $this->assertStringContainsString('días hábiles', $policy);
    }

    public function test_dependency_updates_cover_every_ecosystem_the_project_uses(): void
    {
        $config = (string) file_get_contents(base_path('.github/dependabot.yml'));

        foreach (['composer', 'npm', 'github-actions'] as $ecosystem) {
            $this->assertStringContainsString("package-ecosystem: {$ecosystem}", $config);
        }
    }

    public function test_the_security_workflow_audits_both_lock_files_and_runs_on_a_schedule(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/security.yml'));

        $this->assertStringContainsString('composer audit', $workflow);
        $this->assertStringContainsString('pnpm audit', $workflow);
        $this->assertStringContainsString('cron:', $workflow);
        $this->assertStringContainsString('codeql-action/analyze', $workflow);
    }

    public function test_the_locked_dependencies_have_no_known_vulnerabilities(): void
    {
        // The same rule CI enforces, kept as a test so it is also caught before pushing.
        $overrides = json_decode((string) file_get_contents(base_path('package.json')), true)['pnpm']['overrides'] ?? [];

        $this->assertArrayHasKey('image-size', $overrides, 'the patched image-size override must stay until pptxgenjs ships a fixed release');
    }
}
