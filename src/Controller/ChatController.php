<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Uid\Uuid;
use App\Service\ConversationHistoryService;

class ChatController extends AbstractController
{
    private $client;
    private $apiBaseUrl;
    private $headers;
    private $initialSystemMessage;
    private $conversationHistoryService;

    public function __construct(HttpClientInterface $client, ConversationHistoryService $conversationHistoryService)
    {
        $this->client = $client;
        $this->apiBaseUrl = "https://api.cloudflare.com/client/v4/accounts/5421955b74bc72090671caa5ce3f4442/ai/run/";
        $this->headers = ["Authorization" => "Bearer 1bYav6JeBddRLBnIlPS2IJGNCCdZ2K4hkUDKH6Y7"];
        $this->initialSystemMessage = ["role" => "system", "content" => "Objectif : Vous êtes un chatbot intelligent et amical qui se nomme 'Sam', conçu pour aider les utilisateurs en répondant à leurs questions de manière précise, informative et engageante.
Personnalité : Vous êtes courtois, patient, et prêt à aider. Vous utilisez un langage simple et clair.
Tâches : Répondez aux questions des utilisateurs, fournissez des informations et des suggestions utiles, et engagez la conversation de manière naturelle et fluide.
Limites : Si vous ne savez pas quelque chose, avouez-le honnêtement et, si possible, proposez des moyens pour que l'utilisateur trouve l'information."];
        $this->conversationHistoryService = $conversationHistoryService;
    }

    /**
     * @Route("/chat", name="chat", methods={"POST"})
     */
    public function chat(Request $request): JsonResponse
    {
        $messageData = json_decode($request->getContent(), true);
        $conversationId = $messageData['conversation_id'] ?? Uuid::v4();

        $conversationHistory = $this->conversationHistoryService->getConversationHistory($conversationId);

        if (empty($conversationHistory)) {
            $conversationHistory = [$this->initialSystemMessage];
        }

        $conversationHistory = array_merge($conversationHistory, $messageData['conversation_history']);

        $response = $this->client->request('POST', $this->apiBaseUrl . "@cf/meta/llama-3-8b-instruct", [
            'headers' => $this->headers,
            'json' => ["messages" => $conversationHistory],
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        $output = json_decode($response->getContent(), true);
        $responseMessage = $output['result']['response'];

        $conversationHistory[] = ["role" => "assistant", "content" => $responseMessage];

        $this->conversationHistoryService->setConversationHistory($conversationId, $conversationHistory);

        return new JsonResponse(['response' => $responseMessage]);
    }
}
