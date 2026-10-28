<?php
declare(strict_types=1);

session_start();

const MAX_ERRORS = 6;
const WORD_HINTS = [
    'ABACAXI' => 'FRUTA',
    'BORBOLETA' => 'INSETO',
    'CHOCOLATE' => 'DOCE',
    'ELEFANTE' => 'ANIMAL',
    'GIRASSOL' => 'FLOR',
    'JANELA' => 'PARTE DE UMA CASA',
    'MONTANHA' => 'FORMAÇÃO DA NATUREZA',
    'PIPOCA' => 'COMIDA',
    'TESOURO' => 'OBJETO DE VALOR',
    'VIOLAO' => 'INSTRUMENTO MUSICAL',
];

function newGame(): array
{
    $word = array_rand(WORD_HINTS);
    return [
        'word' => $word,
        'guesses' => [],
        'status' => 'playing',
    ];
}

if (!isset($_SESSION['hangman']) || !is_array($_SESSION['hangman'])) {
    $_SESSION['hangman'] = newGame();
}

$game = $_SESSION['hangman'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'restart') {
        $game = newGame();
        $message = 'Uma nova palavra foi sorteada. Boa sorte!';
    } elseif ($action === 'guess' && $game['status'] === 'playing') {
        $submittedLetter = $_POST['letter'] ?? null;
        $letter = is_string($submittedLetter) ? strtoupper(trim($submittedLetter)) : '';

        if (preg_match('/^[A-Z]$/D', $letter) !== 1) {
            $message = 'Escolha uma letra válida.';
        } elseif (in_array($letter, $game['guesses'], true)) {
            $message = "A letra {$letter} já foi escolhida.";
        } else {
            $game['guesses'][] = $letter;
            $wrongGuesses = array_filter(
                $game['guesses'],
                static fn (string $guess): bool => !str_contains($game['word'], $guess)
            );

            if (count($wrongGuesses) >= MAX_ERRORS) {
                $game['status'] = 'lost';
                $message = 'Fim de jogo! A palavra era ' . $game['word'] . '.';
            } else {
                $won = true;
                foreach (str_split($game['word']) as $character) {
                    if (!in_array($character, $game['guesses'], true)) {
                        $won = false;
                        break;
                    }
                }

                if ($won) {
                    $game['status'] = 'won';
                    $message = 'Parabéns! Você descobriu a palavra!';
                } elseif (str_contains($game['word'], $letter)) {
                    $message = "Boa! A letra {$letter} faz parte da palavra.";
                } else {
                    $message = "A letra {$letter} não está na palavra.";
                }
            }
        }
    }

    $_SESSION['hangman'] = $game;
}

$wrongGuesses = array_filter(
    $game['guesses'],
    static fn (string $guess): bool => !str_contains($game['word'], $guess)
);
$errors = count($wrongGuesses);
$wordIsVisible = $game['status'] !== 'playing';
$hint = WORD_HINTS[$game['word']];
$escapedMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7fb">
    <title>Jogo da Forca</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #20243a;
            background: #f5f7fb;
            font-synthesis: none;
        }

        * { box-sizing: border-box; }

        body {
            min-width: 320px;
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
            background:
                radial-gradient(circle at 12% 8%, #e8edff 0, transparent 30rem),
                #f5f7fb;
        }

        main {
            width: min(100% - 32px, 760px);
            margin: auto;
            padding: 48px 0 32px;
        }

        .card {
            padding: clamp(24px, 6vw, 48px);
            border: 1px solid #e8ebf3;
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 20px 60px rgb(34 45 90 / 9%);
        }

        header { text-align: center; }

        .eyebrow {
            margin: 0 0 8px;
            color: #6558d3;
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(2rem, 7vw, 3rem);
            letter-spacing: -.05em;
        }

        .intro { margin: 10px 0 28px; color: #74798d; }

        .game {
            display: grid;
            grid-template-columns: minmax(150px, 220px) 1fr;
            align-items: center;
            gap: clamp(18px, 5vw, 44px);
        }

        .drawing {
            width: 100%;
            max-width: 220px;
            margin: auto;
            color: #343a56;
        }

        .drawing line, .drawing circle {
            fill: none;
            stroke: currentColor;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 5;
        }

        .drawing .person { stroke: #6558d3; }
        .word {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0 0 8px;
        }

        .hint {
            margin: 0 0 20px;
            color: #74798d;
            text-align: center;
        }

        .hint strong { color: #6558d3; }

        .word span {
            display: grid;
            width: clamp(27px, 7vw, 42px);
            height: 48px;
            place-items: center;
            border-bottom: 3px solid #6558d3;
            color: #282d45;
            font-size: clamp(1.25rem, 4vw, 1.7rem);
            font-weight: 800;
        }

        .status {
            min-height: 48px;
            margin: 0 0 18px;
            color: #74798d;
            line-height: 1.5;
            text-align: center;
        }

        .status strong { color: #282d45; }
        .status .success { color: #17865b; }
        .status .failure { color: #c14956; }

        .keyboard {
            display: grid;
            grid-template-columns: repeat(7, minmax(32px, 1fr));
            gap: 7px;
        }

        button {
            min-height: 42px;
            border: 1px solid #e4e7f0;
            border-radius: 10px;
            background: #f8f9fc;
            color: #353a52;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: background .15s, border-color .15s, transform .15s;
        }

        button:hover:not(:disabled) {
            transform: translateY(-2px);
            border-color: #6558d3;
            background: #f0efff;
        }

        button:focus-visible, .restart:focus-visible {
            outline: 3px solid #a9a2ff;
            outline-offset: 2px;
        }

        button:disabled { cursor: default; opacity: .48; }

        .restart {
            display: block;
            width: 100%;
            margin-top: 20px;
            border: 0;
            background: #6558d3;
            color: #fff;
        }

        .restart:hover { background: #5145bd; }

        footer {
            padding: 0 16px 20px;
            color: #989caf;
            font-size: .8rem;
            text-align: center;
        }

        @media (max-width: 560px) {
            main { padding-top: 24px; }
            .game { grid-template-columns: 1fr; gap: 18px; }
            .drawing { width: 160px; }
        }
    </style>
</head>
<body>
<main>
    <section class="card" aria-labelledby="title">
        <header>
            <p class="eyebrow">Desafio de palavras</p>
            <h1 id="title">Jogo da Forca</h1>
            <p class="intro">Descubra a palavra antes que as tentativas acabem.</p>
        </header>

        <div class="game">
            <svg class="drawing" viewBox="0 0 180 190" role="img" aria-label="Desenho da forca com <?= $errors ?> de <?= MAX_ERRORS ?> erros">
                <line x1="20" y1="174" x2="150" y2="174"/>
                <line x1="52" y1="174" x2="52" y2="18"/>
                <line x1="52" y1="18" x2="125" y2="18"/>
                <line x1="125" y1="18" x2="125" y2="42"/>
                <?php if ($errors >= 1): ?><circle class="person" cx="125" cy="58" r="16"/><?php endif; ?>
                <?php if ($errors >= 2): ?><line class="person" x1="125" y1="74" x2="125" y2="118"/><?php endif; ?>
                <?php if ($errors >= 3): ?><line class="person" x1="125" y1="86" x2="104" y2="105"/><?php endif; ?>
                <?php if ($errors >= 4): ?><line class="person" x1="125" y1="86" x2="146" y2="105"/><?php endif; ?>
                <?php if ($errors >= 5): ?><line class="person" x1="125" y1="118" x2="106" y2="146"/><?php endif; ?>
                <?php if ($errors >= 6): ?><line class="person" x1="125" y1="118" x2="144" y2="146"/><?php endif; ?>
            </svg>

            <div>
                <div class="word" aria-label="Palavra oculta">
                    <?php foreach (str_split($game['word']) as $character): ?>
                        <span><?= $wordIsVisible || in_array($character, $game['guesses'], true) ? htmlspecialchars($character, ENT_QUOTES, 'UTF-8') : '&nbsp;' ?></span>
                    <?php endforeach; ?>
                </div>

                <p class="hint">Dica: <strong><?= htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') ?></strong></p>

                <p class="status" aria-live="polite">
                    <?php if ($message !== ''): ?>
                        <?php
                        $statusClass = $game['status'] === 'won' ? 'success' : ($game['status'] === 'lost' ? 'failure' : '');
                        ?>
                        <strong class="<?= $statusClass ?>"><?= $escapedMessage ?></strong>
                    <?php else: ?>
                        Erros: <strong><?= $errors ?> de <?= MAX_ERRORS ?></strong>
                    <?php endif; ?>
                </p>

                <form method="post" class="keyboard" aria-label="Escolha uma letra">
                    <?php foreach (range('A', 'Z') as $letter): ?>
                        <button
                            type="submit"
                            name="letter"
                            value="<?= $letter ?>"
                            aria-label="Escolher letra <?= $letter ?>"
                            <?= $game['status'] !== 'playing' || in_array($letter, $game['guesses'], true) ? 'disabled' : '' ?>
                        ><?= $letter ?></button>
                    <?php endforeach; ?>
                    <input type="hidden" name="action" value="guess">
                </form>

                <form method="post">
                    <button class="restart" type="submit" name="action" value="restart">Jogar novamente</button>
                </form>
            </div>
        </div>
    </section>
</main>
<footer>Feito com cuidado por Bruno Ares</footer>
</body>
</html>
