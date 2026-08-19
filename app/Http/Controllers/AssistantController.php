<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ChatRequest;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    public function __invoke(): Response
    {
        $messages = auth()->user()->assistantMessages()
            ->oldest()
            ->get()
            ->map(fn ($message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values();

        return Inertia::render('Assistant', [
            'messages' => $messages,
            'status' => 'stub',
        ]);
    }

    public function chat(ChatRequest $request): JsonResponse
    {
        $user = $request->user();
        $content = trim($request->validated()['message']);

        $user->assistantMessages()->create([
            'role' => 'user',
            'content' => $content,
        ]);

        // TODO: Replace the fixed response with the configured AI provider.
        $reply = 'هذه نسخة تجريبية. ستتمكن لاحقاً من سؤالي عن بياناتك المسجّلة.';
        $assistantMessage = $user->assistantMessages()->create([
            'role' => 'assistant',
            'content' => $reply,
        ]);

        return response()->json([
            'message' => [
                'id' => $assistantMessage->id,
                'role' => $assistantMessage->role,
                'content' => $assistantMessage->content,
            ],
            'status' => 'stub',
        ]);
    }
}
