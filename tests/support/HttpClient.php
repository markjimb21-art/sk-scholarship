<?php
declare(strict_types=1);

/** A tiny browser for the test server: keeps its own cookies (one session per client) and the page's CSRF token. */
final class HttpClient
{
    private string $cookieFile;
    public int $status = 0;
    public string $body = '';
    public string $location = '';

    public function __construct(private string $baseUrl)
    {
        $this->cookieFile = (string)tempnam(sys_get_temp_dir(), 'skck');
    }

    public function __destruct() { @unlink($this->cookieFile); }

    public function get(string $path, array $headers = []): self
    {
        return $this->send($path, null, $headers);
    }

    /** POST form fields (and files: field => [path, mime, name]). Adds the CSRF token from $tokenFrom (or the last page). */
    public function post(string $path, array $fields = [], array $files = [], array $headers = [], ?string $tokenFrom = null): self
    {
        if (!array_key_exists('_csrf', $fields)) {
            $page = $tokenFrom !== null ? $this->get($tokenFrom)->body : $this->body;
            $fields['_csrf'] = self::csrfIn($page);
        }
        foreach ($files as $name => [$file, $mime, $filename]) $fields[$name] = new CURLFile($file, $mime, $filename);
        return $this->send($path, $fields, $headers);
    }

    /** Sign in through the real login form; true when it lands on a dashboard. */
    public function login(string $email, string $password): bool
    {
        $this->get('/login');
        $this->post('/login', ['email' => $email, 'password' => $password]);
        return str_contains($this->location, '/dashboard');
    }

    public function json(): ?array
    {
        $data = json_decode($this->body, true);
        return is_array($data) ? $data : null;
    }

    public static function csrfIn(string $html): string
    {
        return preg_match('/name="_csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : '';
    }

    private function send(string $path, ?array $fields, array $headers): self
    {
        $ch = curl_init($this->baseUrl . '/' . ltrim($path, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($fields !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }
        $raw = curl_exec($ch);
        if ($raw === false) throw new RuntimeException('HTTP request failed: ' . curl_error($ch));
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->location = (string)(curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: '');
        $this->body = substr((string)$raw, $headerSize);
        return $this;
    }
}
