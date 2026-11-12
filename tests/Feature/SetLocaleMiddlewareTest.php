<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * The application is in English only: no user, session or browser setting changes the language.
 */
class SetLocaleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private function dispatchMiddleware(Request $request): void
    {
        $middleware = new SetLocale;
        $middleware->handle($request, function () {
            return response('ok');
        });
    }

    private function requestWithSession(): Request
    {
        $request = Request::create('/test');
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    public function test_the_locale_is_english_for_a_visitor(): void
    {
        App::setLocale('fr');

        $this->dispatchMiddleware($this->requestWithSession());

        $this->assertEquals('en', App::getLocale());
        $this->assertEquals('en', Carbon::getLocale());
    }

    public function test_the_locale_of_the_user_is_ignored(): void
    {
        $this->actingAs(User::factory()->create(['locale' => 'es']));

        $this->dispatchMiddleware($this->requestWithSession());

        $this->assertEquals('en', App::getLocale());
        $this->assertEquals('en', Carbon::getLocale());
    }

    public function test_the_locale_of_the_session_is_ignored(): void
    {
        $request = $this->requestWithSession();
        $request->session()->put('locale', 'fr');

        $this->dispatchMiddleware($request);

        $this->assertEquals('en', App::getLocale());
    }

    public function test_the_language_of_the_browser_is_ignored(): void
    {
        $request = $this->requestWithSession();
        $request->headers->set('Accept-Language', 'pt-BR,pt;q=0.9');

        $this->dispatchMiddleware($request);

        $this->assertEquals('en', App::getLocale());
    }
}
