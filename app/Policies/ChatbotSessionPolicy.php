<?php

namespace App\Policies;

use App\Models\ChatbotSession;
use App\Models\User;

class ChatbotSessionPolicy
{
    public function view(User $user, ChatbotSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    public function update(User $user, ChatbotSession $session): bool
    {
        return $session->user_id === $user->id;
    }

    public function delete(User $user, ChatbotSession $session): bool
    {
        return $session->user_id === $user->id;
    }
}
