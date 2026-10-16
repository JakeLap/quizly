<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Support\QuizlyConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * Remember the language picked with the language switcher.
     */
    public function update(Request $request): RedirectResponse
    {
        $locale = $request->validate([
            'locale' => ['required', 'string', Rule::in(QuizlyConfig::locales())],
        ])['locale'];

        return back()->withCookie(Cookie::forever(SetLocale::COOKIE, $locale));
    }
}
