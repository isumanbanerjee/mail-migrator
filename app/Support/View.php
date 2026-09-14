<?php
declare(strict_types=1);

namespace App\Support;

final class View
{
    public function __construct(private string $dir, private ?Auth $auth = null) {}

    public function render(string $template, array $data = []): string
    {
        if (!array_key_exists('currentUser', $data)) {
            $data['currentUser'] = $this->auth?->user();
        }
        $content = $this->renderPartial($template, $data);
        return $this->renderPartial('layout', $data + ['content' => $content]);
    }

    private function renderPartial(string $template, array $data): string
    {
        $e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        extract($data, EXTR_SKIP);
        ob_start();
        include $this->dir . '/' . $template . '.php';
        return (string) ob_get_clean();
    }
}
