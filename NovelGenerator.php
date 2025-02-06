<?php

class NovelState
{
    public string $novelFile;
    public array $meta;
    public string $summary = '';
    public array $chapters = [];
    public int $currentChapter = 1;
    public int $currentPart = 1;
    public int $totalChapters = 12;
    public int $totalPartsPerChapter = 8;
    public array $characters = [];

    public function __construct(string $novelFile, array $novelData)
    {
        $this->novelFile = $novelFile;
        $this->meta = [
            'title' => $novelData['title'],
            'genre' => $novelData['genre'],
            'author1' => $novelData['author1'],
            'author2' => $novelData['author2'],
            'description' => $novelData['description'],
            'summary' => '', // Will be generated
        ];
    }

    public function generateNext(): string
    {
        if (empty($this->summary)) {
            return $this->generateSummary();
        }

        if ($this->currentChapter > $this->totalChapters) {
            return $this->completeGeneration();
        }

        if (!isset($this->chapters[$this->currentChapter])) {
            return $this->generateChapterTitle();
        }

        return $this->generatePartContent();
    }

    private function generateSummary(): string
    {
        $prompt = "Générez un résumé complet et précis pour un roman intitulé '{$this->meta['title']}' dans le genre {$this->meta['genre']}. L'histoire parle de {$this->meta['description']}. Tu dois repondre en français, toujours";

        $summary = $this->callApi($prompt);

        if ($summary === null) {
            return $this->createErrorResponse('Error generating summary.');
        }

        $this->meta['summary'] = $summary;
        $this->summary = $summary;
        $this->saveState();

        return $this->createResponse([
            'status' => 'Generating novel summary...',
            'percentage' => 0,
        ]);
    }

    private function generateChapterTitle(): string
    {
        $chapterNumber = $this->currentChapter;
        $prompt = "Suggérez un titre pour le chapitre {$chapterNumber} d'un roman {$this->meta['genre']} intitulé '{$this->meta['title']}'. Voici un résumé de l'histoire : {$this->summary} Tu dois repondre en français, toujours";

        $chapterTitle = $this->callApi($prompt);

        if ($chapterTitle === null) {
            return $this->createErrorResponse("Error generating title for chapter {$chapterNumber}.");
        }

        $this->chapters[$chapterNumber] = [
            'title' => $chapterTitle,
            'parts' => [],
        ];
        $this->saveState();

        $percentage = (($chapterNumber - 1) * $this->totalPartsPerChapter) / ($this->totalChapters * $this->totalPartsPerChapter) * 100;

        return $this->createResponse([
            'chapterNumber' => $chapterNumber,
            'chapterTitle' => $chapterTitle,
            'percentage' => $percentage,
            'status' => "Generating title for chapter {$chapterNumber}...",
        ]);
    }

    private function generatePartContent(): string
    {
        $chapterNumber = $this->currentChapter;
        $partNumber = $this->currentPart;
        $chapterTitle = $this->chapters[$chapterNumber]['title'];

        $prompt = "Écrivez la partie {$partNumber} du chapitre {$chapterNumber} intitulé '{$chapterTitle}' dans un roman intitulé '{$this->meta['title']}'. Le genre du roman est {$this->meta['genre']}. L'histoire parle de {$this->meta['description']}. Voici le résumé : {$this->summary}. Tu dois repondre en français, toujours";

        if ($partNumber > 1 && isset($this->chapters[$chapterNumber]['parts'][$partNumber - 1])) {
            $previousPart = $this->chapters[$chapterNumber]['parts'][$partNumber - 1];
            $prompt .= " The previous part ended with: {$previousPart}.";
        }

        $partContent = $this->callApi($prompt);

        if ($partContent === null) {
            return $this->createErrorResponse("Error generating part {$partNumber} of chapter {$chapterNumber}.");
        }

        $this->chapters[$chapterNumber]['parts'][$partNumber] = $partContent;
        $this->saveState();

        $percentage = (($chapterNumber - 1) * $this->totalPartsPerChapter + $partNumber) / ($this->totalChapters * $this->totalPartsPerChapter) * 100;

        // Update chapter and part numbers
        $this->currentPart++;
        if ($this->currentPart > $this->totalPartsPerChapter) {
            $this->currentChapter++;
            $this->currentPart = 1;
        }

        return $this->createResponse([
            'chapterNumber' => $chapterNumber,
            'partNumber' => $partNumber,
            'partContent' => $partContent,
            'percentage' => $percentage,
            'status' => "Generating chapter {$chapterNumber}, part {$partNumber}...",
        ]);
    }

    private function completeGeneration(): string
    {
        return $this->createResponse([
            'status' => 'Novel generation complete!',
            'percentage' => 100,
            'isComplete' => true,
        ]);
    }

    private function callApi(string $prompt): ?string
    {
        $apiKey = MISTRAL_API_KEY;
        $endpoint = MISTRAL_ENDPOINT;
        $model = MISTRAL_MODEL;

        $messages = [
            [
                "role" => "user",
                "content" => $prompt,
            ],
        ];

        $data = [
            "model" => $model,
            "messages" => $messages,
            "max_tokens" => 200,
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            error_log("cURL Error: " . $error);
            curl_close($ch);
            return null;
        }

        $result = json_decode($response, true);
        curl_close($ch);

        if (isset($result['choices'][0]['message']['content'])) {
            return trim($result['choices'][0]['message']['content']);
        } else {
            error_log("Unexpected API response: " . json_encode($result));
            return null;
        }
    }

    private function saveState(): void
    {
        file_put_contents($this->novelFile, json_encode($this, JSON_PRETTY_PRINT));
    }

    private function createResponse(array $data): string
    {
        return json_encode($data);
    }

    private function createErrorResponse(string $errorMessage): string
    {
        return json_encode(['error' => $errorMessage]);
    }
}
