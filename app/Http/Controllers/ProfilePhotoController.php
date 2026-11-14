<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfilePhotoController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=256,min_height=256', 'max:2048'],
        ]);

        $user = $request->user();
        $user->profile_photo_path = null;
        $user->profile_photo_data = $validated['profile_photo']->get();
        $user->profile_photo_mime = $validated['profile_photo']->getMimeType();
        $user->save();

        return back()->with('status', 'Profile photo updated.');
    }

    public function destroy(Request $request)
    {
        DB::table('users')
            ->where('id', $request->user()->id)
            ->update([
                'profile_photo_path' => null,
                'profile_photo_data' => null,
                'profile_photo_mime' => null,
                'updated_at' => now(),
            ]);

        return back()->with('status', 'Profile photo removed.');
    }
}
