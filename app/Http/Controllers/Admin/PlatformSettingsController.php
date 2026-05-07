<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformSettingsController extends Controller
{
    public function index(): Response
    {
        $settings = [
            'site_name' => PlatformSetting::get('site_name', 'EduBridge'),
            'maintenance_mode' => PlatformSetting::get('maintenance_mode', false),
            'student_registration' => PlatformSetting::get('student_registration', true),
            'teacher_registration' => PlatformSetting::get('teacher_registration', true),
            'platform_fee_percent' => PlatformSetting::get('platform_fee_percent', 10),
            'contact_email' => PlatformSetting::get('contact_email', 'support@edubridge.com'),
        ];

        return Inertia::render('Admin/SettingsPlatform', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'maintenance_mode' => 'required|boolean',
            'student_registration' => 'required|boolean',
            'teacher_registration' => 'required|boolean',
            'platform_fee_percent' => 'required|integer|min:0|max:100',
            'contact_email' => 'required|email|max:255',
        ]);

        foreach ($validated as $key => $value) {
            $type = is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'string');
            PlatformSetting::set($key, $value, $type);
        }

        return back()->with('status', 'Platform settings updated successfully.');
    }
}
