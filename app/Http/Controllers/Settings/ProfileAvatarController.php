<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileAvatarUpdateRequest;
use App\Services\TripCovers\TripCoverImageResizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProfileAvatarController extends Controller
{
    /**
     * Square size (in pixels) that uploaded profile pictures are cropped to.
     */
    private const AVATAR_SIZE = 400;

    /**
     * Show the profile picture settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/ProfilePicture');
    }

    /**
     * Store a new profile picture for the user, replacing any existing one.
     */
    public function update(ProfileAvatarUpdateRequest $request, TripCoverImageResizer $resizer): RedirectResponse
    {
        $user = $request->user();

        $avatarBytes = $resizer->toJpegThumbnail(
            $request->file('avatar')->getContent(),
            self::AVATAR_SIZE,
            self::AVATAR_SIZE,
            85,
        );

        if ($avatarBytes === null) {
            return back()->withErrors([
                'avatar' => __('The profile picture could not be processed. Please try another image.'),
            ]);
        }

        $path = "avatars/{$user->id}/".Str::random(20).'.jpg';

        Storage::disk('public')->put($path, $avatarBytes);

        $previousPath = $user->avatar_path;

        $user->forceFill(['avatar_path' => $path])->save();

        if (filled($previousPath)) {
            Storage::disk('public')->delete($previousPath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile picture updated.')]);

        return to_route('profile-picture.edit');
    }

    /**
     * Remove the user's profile picture.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (filled($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);

            $user->forceFill(['avatar_path' => null])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile picture removed.')]);

        return to_route('profile-picture.edit');
    }
}
