<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Uid\Uuid;

class ChatController extends AbstractController
{
    private $client;
    private $apiBaseUrl;
    private $headers;
    private $initialSystemMessage;
    private $conversationHistory;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        $this->apiBaseUrl = "https://api.cloudflare.com/client/v4/accounts/5421955b74bc72090671caa5ce3f4442/ai/run/";
        $this->headers = ["Authorization" => "Bearer 1bYav6JeBddRLBnIlPS2IJGNCCdZ2K4hkUDKH6Y7"];
        $this->initialSystemMessage = ["role" => "system", "content" => "Tu t'appeles Sam et tu es un assistant. Répond de façon façon brute et simple uniquement en français."];
        $this->conversationHistory = [];
    }

    #[Route('/chat', name: 'chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $messageData = json_decode($request->getContent(), true);
        $conversationId = $messageData['conversation_id'] ?? Uuid::v4();

        if (!isset($this->conversationHistory[$conversationId])) {
            $this->conversationHistory[$conversationId] = [$this->initialSystemMessage];
        }

        $message = $messageData['message'];
        $this->conversationHistory[$conversationId][] = ["role" => "user", "content" => $message];

        $response = $this->client->request('POST', $this->apiBaseUrl . "@cf/mistral/mistral-7b-instruct-v0.1", [
            'headers' => $this->headers,
            'json' => ["messages" => $this->conversationHistory[$conversationId]],
            'verify_peer' => false,
            'verify_host' => false,
        ]);
        $output = json_decode($response->getContent(), true);
        $responseMessage = $output['result']['response'];

        $this->conversationHistory[$conversationId][] = ["role" => "assistant", "content" => $responseMessage];

        return new JsonResponse(['response' => $responseMessage]);
    }
}
