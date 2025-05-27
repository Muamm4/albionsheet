<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{

    public function login()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
    
            $user = User::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => bcrypt(Str::random(20)),
                ]
            );
    
            // Atualizar o google_id e avatar se o usuário já existia (caso mude)
            if (!$user->wasRecentlyCreated && !$user->google_id) {
                $user->google_id = $googleUser->getId();
                $user->avatar = $googleUser->getAvatar();
                $user->save();
            }
    
            // Fazer login do usuário no Laravel
            Auth::login($user);
    
            return redirect()->route('home'); // Redirecionando para a rota 'home' que já existe
    
        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'Ocorreu um erro ao tentar fazer login com o Google.');
        }
    }
}
