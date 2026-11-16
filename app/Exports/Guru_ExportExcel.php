<?php

namespace App\Exports;

use App\Models\guru;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class Guru_ExportExcel implements FromView
{
    public function view(): View
    {
        return view('Export.guru.excel', [
            'guru' => guru::orderBy('nama_guru')->get(),
        ]);
    }
}