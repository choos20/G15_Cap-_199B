<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $mime = $user->profile_photo_mime ?: 'image/jpeg';
        $data = $user->profile_photo_data;

        if (is_resource($data)) {
            $data = stream_get_contents($data);
        }

        if (is_string($data) && str_starts_with($data, '\\x')) {
            $data = hex2bin(substr($data, 2));
        }

        if ($data) {
            return response($data)->header('Content-Type', $mime)->header('Cache-Control', 'private, max-age=300');
        }

        if ($user->profile_photo_path && Storage::disk('public')->exists($user->profile_photo_path)) {
            return response(Storage::disk('public')->get($user->profile_photo_path))
                ->header('Content-Type', Storage::disk('public')->mimeType($user->profile_photo_path))
                ->header('Cache-Control', 'private, max-age=300');
        }

        abort(404);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=256,min_height=256', 'max:2048'],
        ]);

        $user = $request->user();
        $oldPath = $user->profile_photo_path;

        if (Schema::hasColumn('users', 'profile_photo_data')) {
            $user->profile_photo_path = null;
            $user->profile_photo_data = DB::raw("decode('" . base64_encode($validated['profile_photo']->get()) . "', 'base64')");
            $user->profile_photo_mime = $validated['profile_photo']->getMimeType();
        } else {
            $user->profile_photo_path = $validated['profile_photo']->store('profile_photos', 'public');
        }

        $user->save();

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('status', 'Profile photo updated.');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        $oldPath = $user->profile_photo_path;

        if (Schema::hasColumn('users', 'profile_photo_data')) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'profile_photo_path' => null,
                    'profile_photo_data' => null,
                    'profile_photo_mime' => null,
                    'updated_at' => now(),
                ]);
        } else {
            $user->profile_photo_path = null;
            $user->save();
        }

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return back()->with('status', 'Profile photo removed.');
    }
}
