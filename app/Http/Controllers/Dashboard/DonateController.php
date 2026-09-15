<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateDonateConfigRequest;
use App\Models\DonateConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DonateController extends Controller
{
    public function edit(): View
    {
        return view('dashboard.donate', [
            'config' => DonateConfig::current(),
        ]);
    }

    public function update(UpdateDonateConfigRequest $request): RedirectResponse
    {
        DonateConfig::current()->update($request->validated());

        return redirect()->route('dashboard.donate')->with('status', 'Donate settings updated.');
    }
}
