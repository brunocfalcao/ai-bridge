<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Knowledge\Middleware;

use BrunoCFalcao\AiBridge\Knowledge\SystemContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function __construct(
        protected SystemContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = basename($request->path());

        $applicationModel = config('ai-bridge.models.application');

        if (! $applicationModel) {
            abort(404, 'System not found.');
        }

        $application = $applicationModel::where('slug', $slug)
            ->where('status', 'active')
            ->whereNotNull('knowledge_connection')
            ->first();

        if (! $application) {
            abort(404, 'System not found.');
        }

        if ($request->bearerToken() !== $application->mcp_api_key) {
            abort(401, 'Invalid API key.');
        }

        $this->context->setSlug($slug);

        return $next($request);
    }
}
