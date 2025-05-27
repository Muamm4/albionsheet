<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{

    /**
     * Redireciona o usuário para a página de autenticação do Google
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function login()
    {
        try {
            return Socialite::driver('google')
                ->redirect();
        } catch (\Exception $e) {
            Log::error('Erro ao redirecionar para o Google: ' . $e->getMessage());
            return redirect()->route('login')->with('error', 'Não foi possível conectar ao Google. Tente novamente mais tarde.');
        }
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
    
            return redirect()->route('albion.index'); // Redirecionando para a rota 'home' que já existe
    
        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'Ocorreu um erro ao tentar fazer login com o Google.');
        }
    }
}
