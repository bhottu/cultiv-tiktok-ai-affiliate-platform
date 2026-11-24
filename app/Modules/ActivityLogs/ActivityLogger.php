<?php

namespace App\Modules\ActivityLogs;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public function log(
        string $module,
        string $action,
        string $description,
        string $level = 'info',
        array $context = [],
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?int $userId = null,
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'module' => $module,
            'action' => $action,
            'level' => $level,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'description' => $this->redact($description),
            'context' => $this->sanitizeContext($context),
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    public function redact(string $text): string
    {
        $patterns = [
            '/(Bearer\s+)[A-Za-z0-9\-._~+\/]+=*/i',
            '/(x-goog-api-key:\s*)[A-Za-z0-9\-_]+/i',
            '/(sk-or-v1-)[A-Za-z0-9]+/i',
            '/(AIza)[0-9A-Za-z\-_]{10,}/',
            '/(act\.|rft\.)[A-Za-z0-9\-._~!]+/',
            '/(client_secret=|access_token=|refresh_token=|key=)[^&\s]+/i',
        ];

        return (string) preg_replace($patterns, '$1[REDACTED]', $text);
    }

    private function sanitizeContext(array $context): array
    {
        $clean = [];
        foreach ($context as $k => $v) {
            if (preg_match('/key|secret|token|password|credential/i', (string) $k)) {
                $clean[$k] = '[REDACTED]';
            } elseif (is_string($v)) {
                $clean[$k] = $this->redact($v);
            } elseif (is_array($v)) {
                $clean[$k] = $this->sanitizeContext($v);
            } else {
                $clean[$k] = $v;
            }
        }

        return $clean;
    }
}
