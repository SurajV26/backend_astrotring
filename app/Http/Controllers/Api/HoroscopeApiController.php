<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Horoscope;
use App\Models\ZodiacSign;
use App\Services\ZodiacForecast;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class HoroscopeApiController extends Controller
{
    public function index(Request $request, ZodiacForecast $forecast)
    {
        $types = $request->filled('type') ? [$request->query('type')] : ZodiacForecast::TYPES;
        $signs = ZodiacSign::where('status', 1)->whereIn('slug', ZodiacForecast::SIGNS)
            ->when($request->filled('zodiac_id'), fn ($query) => $query->where('id', $request->integer('zodiac_id')))
            ->orderBy('id')->get();
        $today = CarbonImmutable::today('Asia/Kolkata');
        $rows = [];
        foreach ($types as $type) {
            if (!in_array($type, ZodiacForecast::TYPES, true)) {
                continue;
            }
            foreach ($signs as $sign) {
                $rows[] = $forecast->generate($sign, $type, $today);
            }
        }
        return response()->json(['status' => true, 'count' => count($rows), 'data' => $rows]);
    }

    public function show(string $id, ZodiacForecast $forecast)
    {
        $signId = $type = null;
        if (ctype_digit($id) && (int) $id >= ZodiacForecast::ID_BASE) {
            $code = (int) $id - ZodiacForecast::ID_BASE;
            $signId = intdiv($code, 10);
            $type = ZodiacForecast::TYPES[$code % 10] ?? null;
        } elseif (ctype_digit($id)) {
            // Resolve bookmarks made before forecasts had virtual IDs.
            $legacy = Horoscope::where('status', 1)->find($id, ['zodiac_id', 'type']);
            $signId = $legacy?->zodiac_id;
            $type = $legacy?->type;
        }
        $sign = ZodiacSign::where('status', 1)->whereIn('slug', ZodiacForecast::SIGNS)->find($signId);
        if (!$sign || !in_array($type, ZodiacForecast::TYPES, true)) {
            return response()->json(['status' => false, 'message' => 'Data not found'], 404);
        }
        $data = $forecast->generate($sign, $type, CarbonImmutable::today('Asia/Kolkata'), true);
        $data['id'] = (int) $id;
        return response()->json(['status' => true, 'data' => $data]);
    }
}
