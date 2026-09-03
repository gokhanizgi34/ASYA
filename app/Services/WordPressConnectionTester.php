<?php

namespace App\Services;

use App\PublishingProtocol;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class WordPressConnectionTester
{
    public function __construct(
        private readonly ExternalUrlGuard $urlGuard,
    ) {}

    /** @return array{successful: bool, message: string} */
    public function test(string $baseUrl, PublishingProtocol $protocol, string $username, string $credential): array
    {
        try {
            $this->urlGuard->assertSafe($baseUrl, false);

            return $protocol === PublishingProtocol::WordPressRest
                ? $this->testRest($baseUrl, $username, $credential)
                : $this->testXmlRpc($baseUrl, $username, $credential);
        } catch (Throwable $exception) {
            return ['successful' => false, 'message' => Str::limit($exception->getMessage(), 1000, '…')];
        }
    }

    /** @return array{successful: bool, message: string} */
    private function testRest(string $baseUrl, string $username, string $credential): array
    {
        $response = $this->request($username, $credential)
            ->get(rtrim($baseUrl, '/').'/wp-json/wp/v2/users/me', ['context' => 'edit']);

        return $response->successful()
            ? ['successful' => true, 'message' => 'WordPress REST bağlantısı doğrulandı.']
            : ['successful' => false, 'message' => 'WordPress REST bağlantısı HTTP '.$response->status().' döndürdü.'];
    }

    /** @return array{successful: bool, message: string} */
    private function testXmlRpc(string $baseUrl, string $username, string $credential): array
    {
        $xml = '<?xml version="1.0"?><methodCall><methodName>wp.getUsersBlogs</methodName><params>'
            .'<param><value><int>0</int></value></param>'
            .'<param><value><string>'.htmlspecialchars($username, ENT_XML1).'</string></value></param>'
            .'<param><value><string>'.htmlspecialchars($credential, ENT_XML1).'</string></value></param>'
            .'</params></methodCall>';
        $response = Http::connectTimeout(5)->timeout(15)
            ->withBody($xml, 'text/xml')
            ->post(rtrim($baseUrl, '/').'/xmlrpc.php');

        if ($response->successful() && ! str_contains($response->body(), '<fault>')) {
            return ['successful' => true, 'message' => 'WordPress XML-RPC bağlantısı doğrulandı.'];
        }

        return ['successful' => false, 'message' => 'WordPress XML-RPC bağlantısı doğrulanamadı.'];
    }

    private function request(string $username, string $credential): PendingRequest
    {
        return Http::connectTimeout(5)->timeout(15)->acceptJson()->withBasicAuth($username, $credential);
    }
}
