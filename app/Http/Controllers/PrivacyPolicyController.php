<?php

namespace App\Http\Controllers;

use App\Domain\Dashboards\Actions\UploadDataset;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public privacy policy. Its facts (who handles the data, how long files are kept, the
 * session lifetime) come from the same configuration the application runs on, so the page
 * cannot say something the code no longer does.
 */
class PrivacyPolicyController extends Controller
{
    public function __invoke(): Response
    {
        $email = config('privacy.contact_email');

        return Inertia::render('legal/Privacy', [
            'version' => (string) config('privacy.version'),
            'updatedAt' => (string) config('privacy.updated_at'),
            'controller' => config('privacy.controller'),
            'contactEmail' => is_string($email) && $email !== '' ? $email : null,
            'processors' => config('privacy.processors'),
            'retention' => [
                'pendingUploadHours' => UploadDataset::ABANDONED_AFTER_HOURS,
                'sessionMinutes' => (int) config('session.lifetime'),
            ],
        ]);
    }
}
