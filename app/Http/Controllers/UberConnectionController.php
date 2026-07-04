<?php

namespace App\Http\Controllers;

use App\Services\UberDriverApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UberConnectionController extends Controller
{
    public function redirect(Request $request, UberDriverApi $uber): RedirectResponse
    {
        abort_unless($request->user(), 403);

        if (! $uber->isConfigured()) {
            return redirect()->route('profile.edit')
                ->with('uber_status', 'Conexao Uber indisponivel: configure UBER_CLIENT_ID, UBER_CLIENT_SECRET e UBER_REDIRECT_URI.');
        }

        $state = Str::uuid()->toString();
        $request->session()->put('uber_oauth_state', $state);

        return redirect()->away($uber->redirectUrl($state));
    }

    public function callback(Request $request, UberDriverApi $uber): RedirectResponse
    {
        abort_unless($request->user(), 403);

        if ($request->string('state')->toString() !== $request->session()->pull('uber_oauth_state')) {
            return redirect()->route('profile.edit')->with('uber_status', 'Falha de seguranca na autenticacao com a Uber.');
        }

        if ($request->filled('error')) {
            return redirect()->route('profile.edit')->with('uber_status', 'A Uber recusou a conexao: '.$request->string('error')->toString().'.');
        }

        $connection = $uber->connect($request->user(), $request->string('code')->toString());
        $synced = $uber->syncTrips($connection);

        return redirect()->route('profile.edit')
            ->with('uber_status', "Conta Uber conectada e {$synced} corridas sincronizadas.");
    }

    public function sync(Request $request, UberDriverApi $uber): RedirectResponse
    {
        $connection = $request->user()->uberConnection;

        if (! $connection) {
            return redirect()->route('profile.edit')->with('uber_status', 'Nenhuma conta Uber vinculada.');
        }

        $synced = $uber->syncTrips($connection);

        return redirect()->route('profile.edit')->with('uber_status', "{$synced} corridas Uber sincronizadas.");
    }

    public function destroy(Request $request, UberDriverApi $uber): RedirectResponse
    {
        $connection = $request->user()->uberConnection;

        if ($connection) {
            $uber->disconnect($connection);
        }

        return redirect()->route('profile.edit')->with('uber_status', 'Conexao Uber removida.');
    }
}
