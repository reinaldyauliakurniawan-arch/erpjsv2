<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class TestAllRoutes extends Command
{
    protected $signature = 'test:routes {--url=http://localhost:8000} {--email=admin@example.com} {--password=password}';
    protected $description = 'Test semua route dan laporkan yang error';

    public function handle()
    {
        $base  = $this->option('url');
        $email = $this->option('email');
        $pass  = $this->option('password');

        $jar = new \GuzzleHttp\Cookie\CookieJar();

        Http::withOptions(['verify' => false, 'cookies' => $jar])
            ->post("{$base}/login", ['email' => $email, 'password' => $pass]);

        $skip    = ['logout', 'password', 'verify-email', 'storage/'];
        $methods = ['POST', 'PUT', 'PATCH', 'DELETE'];
        $results = [];

        foreach (Route::getRoutes() as $route) {
            $uri    = $route->uri();
            $method = collect($route->methods())->intersect($methods)->first();

            if (!$method) continue;
            if (collect($skip)->contains(fn($s) => str_contains($uri, $s))) continue;

            $url      = $base . '/' . preg_replace('/\{[^}]+\}/', '1', $uri);
            $response = Http::withOptions(['verify' => false, 'cookies' => $jar])
                ->withHeaders(['Accept' => 'application/json'])
                ->{strtolower($method)}($url, []);

            $status    = $response->status();
            $results[] = ['method' => $method, 'url' => $url, 'status' => $status, 'body' => $response->body()];
        }

        $errors = collect($results)->filter(fn($r) => $r['status'] >= 500);
        $ok     = collect($results)->filter(fn($r) => $r['status'] < 500);

        $this->info("OK     : " . $ok->count());
        $this->error("ERROR  : " . $errors->count());
        $this->newLine();

        foreach ($errors as $r) {
            $this->error("{$r['method']} {$r['url']} => {$r['status']}");
            $this->line(substr($r['body'], 0, 200));
            $this->newLine();
        }
    }
}
