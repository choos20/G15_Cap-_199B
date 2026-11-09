<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=256,min_height=256', 'max:2048'],
        ]);

        $user = $request->user();
        $newPath = $validated['profile_photo']->store('profile_photos', 'public');
        if (! is_string($newPath) || $newPath === '') {
            throw new \RuntimeException('The profile photo could not be stored.');
        }

        $oldPath = $user->profile_photo_path;
        $user->profile_photo_path = $newPath;
        $user->save();
        $this->deleteStoredPhoto($oldPath, 'profile_photos/');

        return back()->with('status', 'Profile photo updated.');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        $oldPath = $user->profile_photo_path;
        $user->profile_photo_path = null;
        $user->save();
        $this->deleteStoredPhoto($oldPath, 'profile_photos/');

        return back()->with('status', 'Profile photo removed.');
    }

    private function deleteStoredPhoto(?string $path, string $directory): void
    {
        if ($path && str_starts_with($path, $directory)) {
            Storage::disk('public')->delete($path);
        }
    }
}
