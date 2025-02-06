<?php

// Configuration
define('MISTRAL_API_KEY', ' YOUR API KEY HERE ');
define('MISTRAL_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');
define('MISTRAL_MODEL', 'mistral-small');
define('DELAY_SECONDS', 2);
define('DEBUG_MODE', true);

// Session Start
session_start();

// Include Classes
require_once 'NovelGenerator.php'; // Contains NovelState and other classes

// Get IP address
$ip_address = $_SERVER['REMOTE_ADDR'];
$novel_file = 'novels/' . md5($ip_address) . '.json';

// Form Handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save Novel Information to Session
    $_SESSION['novel'] = [
        'title' => $_POST['title'],
        'genre' => $_POST['genre'],
        'author1' => $_POST['author1'],
        'author2' => $_POST['author2'],
        'description' => $_POST['description'],
    ];

    // Ensure novels directory exists
    if (!is_dir('novels')) {
        mkdir('novels', 0777, true);
    }

    // Start New Novel Generation
    $novelState = new NovelState($novel_file, $_SESSION['novel']);
    $_SESSION['novel_state'] = serialize($novelState);

}

// Generation Handling
if (isset($_GET['generate']) && $_GET['generate'] === 'true' && isset($_SESSION['novel_state'])) {
    // Unserialize state
    $novelState = unserialize($_SESSION['novel_state']);

    // Generate next part
    $result = $novelState->generateNext();

    // Serialize state
    $_SESSION['novel_state'] = serialize($novelState);

    echo $result;
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novel Generator</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            font-family: sans-serif;
            background-color: #f8f9fa;
        }

        .container {
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            font-weight: bold;
        }

        #progress {
            margin-bottom: 20px;
        }

        #output {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 20px;
            height: 300px;
            overflow-y: scroll;
            background-color: #fff;
        }

        .chapter {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            padding: 10px;
            background-color: #fff;
        }

        .part {
            margin-bottom: 10px;
            padding: 5px;
            border-left: 3px solid #007bff;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Novel Generator</h1>

    <form method="post">
        <div class="form-group">
            <label for="title">Title of Your Novel:</label>
            <input type="text" class="form-control" id="title" name="title" required>
        </div>

        <div class="form-group">
            <label for="description">What's your novel about?</label>
            <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
        </div>

        <div class="form-group">
            <label for="genre">Literary Style:</label>
            <select class="form-control" id="genre" name="genre">
                <option value="science_fiction">Science Fiction</option>
                <option value="fantasy">Biographie</option>
                <option value="mystery">Roman classique</option>
                <option value="romance">Grand roman de la pleiade</option>
                <option value="thriller">Roman moderne</option>
                <option value="historical_fiction">Roman punk underground</option>
            </select>
        </div>

        <div class="form-group">
            <label for="author1">Favorite Author 1:</label>
            <select class="form-control" id="author1" name="author1">
                <option value="tolkien">Louis Ferdinand Celine</option>
                <option value="asimov">Isaac Asimov</option>
                <option value="dumas">Alexandre Dumas</option>
            </select>
        </div>

        <div class="form-group">
            <label for="author2">Favorite Author 2:</label>
            <select class="form-control" id="author2" name="author2">
                <option value="lovecraft">John Steinbeck</option>
                <option value="dickens">Dostoiwesky</option>
                <option value="orwell">Vladimir Nabokov</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Start Generating Novel</button>
    </form>

    <div id="progress" class="progress">
        <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0"
             aria-valuemin="0" aria-valuemax="100">0%
        </div>
    </div>
    <div id="status"></div>

    <div id="novel-content">
        <h2>Novel Content</h2>
        <div id="chapters">
            <!-- Chapters will be dynamically added here -->
        </div>
    </div>

    <?php if (isset($_SESSION['novel_state'])): ?>
        <a id="download-link" href="<?php echo $novel_file; ?>" style="display: none;">Download Novel</a>
    <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
    function updateProgressBar(percentage) {
        document.getElementById('progress-bar').style.width = percentage + '%';
        document.getElementById('progress-bar').innerText = percentage + '%';
        document.getElementById('progress-bar').setAttribute('aria-valuenow', percentage);
    }

    function appendChapter(chapterNumber, chapterTitle) {
        const chaptersDiv = document.getElementById('chapters');
        const chapterDiv = document.createElement('div');
        chapterDiv.classList.add('chapter');
        chapterDiv.id = 'chapter-' + chapterNumber;
        chapterDiv.innerHTML = `<h3>Chapter ${chapterNumber}: ${chapterTitle}</h3><div id="parts-${chapterNumber}"></div>`;
        chaptersDiv.appendChild(chapterDiv);
    }

    function appendPart(chapterNumber, partNumber, partContent) {
        const partsDiv = document.getElementById('parts-' + chapterNumber);
        const partDiv = document.createElement('div');
        partDiv.classList.add('part');
        partDiv.id = 'part-' + chapterNumber + '-' + partNumber;
        partDiv.innerHTML = `<p>${partContent}</p>`;
        partsDiv.appendChild(partDiv);
    }

    function generateNext() {
        fetch('install.php?generate=true')
            .then(response => response.text())
            .then(data => {
                // Check for error conditions
                if (data.startsWith('Error')) {
                    document.getElementById('status').innerText = data;
                    return; // Stop generation on error
                }

                try {
                    const responseData = JSON.parse(data);

                    // Check for general errors
                    if (responseData.error) {
                        document.getElementById('status').innerText = responseData.error;
                        return;
                    }

                    const chapterNumber = responseData.chapterNumber;
                    const partNumber = responseData.partNumber;
                    const chapterTitle = responseData.chapterTitle;
                    const partContent = responseData.partContent;
                    const isComplete = responseData.isComplete;
                    const percentage = responseData.percentage;
                    const status = responseData.status;

                    // Update UI elements
                    document.getElementById('status').innerText = status;
                    updateProgressBar(percentage);

                    if (chapterTitle) {
                        appendChapter(chapterNumber, chapterTitle);
                    }

                    if (partContent) {
                        appendPart(chapterNumber, partNumber, partContent);
                    }

                    if (isComplete) {
                        document.getElementById('status').innerText = "Novel generation complete!";
                        document.getElementById('download-link').style.display = 'inline';
                    } else {
                        // Generate next part
                        setTimeout(generateNext, <?php echo DELAY_SECONDS * 1000; ?>); // Call every 2 seconds
                    }
                } catch (e) {
                    console.error("Error parsing JSON response:", e);
                    document.getElementById('status').innerText = "Error processing response from server.";
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('status').innerText = "Error generating novel!";
            });
    }

    <?php if (isset($_SESSION['novel_state'])): ?>
    // Start generating the novel
    generateNext();
    <?php endif; ?>
</script>

</body>
</html>
