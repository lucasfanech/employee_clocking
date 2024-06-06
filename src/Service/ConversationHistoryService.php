<?php

namespace App\Service;

class ConversationHistoryService
{
    private $conversationHistory = [];

    public function getConversationHistory(string $conversationId): array
    {
        return $this->conversationHistory[$conversationId] ?? [];
    }

    public function setConversationHistory(string $conversationId, array $conversationHistory): void
    {
        $this->conversationHistory[$conversationId] = $conversationHistory;
    }
}
