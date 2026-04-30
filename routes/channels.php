<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) {
    $conversation = \App\Models\Conversation::query()->find($conversationId);

    if (! $conversation) {
        return false;
    }

    $isParticipant = $conversation->is_group
        ? \App\Models\ClassMember::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists()
        : \App\Models\ConversationParticipant::query()
            ->where('conversation_id', $conversationId)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();

    if (! $isParticipant) return false;

    $channelName = request()->input('channel_name', '');
    if (str_starts_with($channelName, 'presence-')) {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
        ];
    }

    return true;
});
