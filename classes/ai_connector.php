<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * AI connector for block_questionfilter.
 *
 * Supports Ollama (local) and Claude API as interchangeable backends.
 * Question data is anonymised before sending: no author IDs, no
 * user-generated filenames, no Moodle-internal identifiers.
 *
 * @package    block_questionfilter
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_questionfilter;

/**
 * Sends anonymised question text to a configured AI backend and
 * returns a list of suggested tags.
 */
class ai_connector {
    /** @var string Active backend: 'ollama' or 'claude'. */
    private string $backend;

    /** @var array Plugin configuration values. */
    private array $config;

    /**
     * Constructor: loads plugin config once.
     */
    public function __construct() {
        $this->backend = get_config('block_questionfilter', 'ai_backend') ?: 'ollama';
        $this->config  = (array) get_config('block_questionfilter');
    }

    /**
     * Generates tag suggestions for a question.
     *
     * Only the question name and plain text of the question body are sent.
     * Author IDs, category IDs, course IDs, and Moodle-internal metadata
     * are stripped before the request leaves the server.
     *
     * @param string $questionname  Fragenname (Titel).
     * @param string $questiontext  Fragetext (HTML wird zu Plain-Text).
     * @param string $qtype         Interner Fragetyp-Schluessel.
     * @return string[]             Liste vorgeschlagener Tags (max. ai_max_tags).
     */
    public function suggest_tags(
        string $questionname,
        string $questiontext,
        string $qtype
    ): array {
        $maxtags    = (int)(get_config('block_questionfilter', 'ai_max_tags') ?: 5);
        $categories = get_config('block_questionfilter', 'ai_tag_categories') ?: '';

        // Fragetext: HTML entfernen, auf 800 Zeichen kuerzen (Datensparsamkeit).
        $plaintext = strip_tags($questiontext);
        $plaintext = html_entity_decode($plaintext, ENT_QUOTES, 'UTF-8');
        $plaintext = preg_replace('/\s+/', ' ', trim($plaintext));
        if (mb_strlen($plaintext) > 800) {
            $plaintext = mb_substr($plaintext, 0, 800) . ' …';
        }

        $prompt = $this->build_prompt($questionname, $plaintext, $qtype, $maxtags, $categories);

        $raw = $this->backend === 'claude'
            ? $this->call_claude($prompt)
            : $this->call_ollama($prompt);

        return $this->parse_tags($raw, $maxtags);
    }

    /**
     * Builds the prompt sent to the AI.
     * Kept short to reduce token use and processing time.
     *
     * @param string $name       Fragenname.
     * @param string $text       Bereinigter Fragetext.
     * @param string $qtype      Fragetyp.
     * @param int    $max        Maximale Anzahl Tags.
     * @param string $categories Zeilengetrennte Themenkategorien.
     * @return string            Fertiger Prompt.
     */
    private function build_prompt(
        string $name,
        string $text,
        string $qtype,
        int $max,
        string $categories
    ): string {
        $catlist = '';
        if (!empty($categories)) {
            $cats    = array_filter(array_map('trim', explode("\n", $categories)));
            $catlist = "\nMoegliche Fachgebiete (falls passend): " . implode(', ', $cats) . '.';
        }

        return "Du bist ein Schlagwort-Generator fuer Schulaufgaben."
            . " Analysiere die folgende Pruefungsfrage und schlage maximal {$max} deutschsprachige"
            . " Schlagworte vor, die Fachgebiet, Thema und Schwierigkeitsgrad beschreiben."
            . $catlist
            . "\n\nFragentyp: {$qtype}"
            . "\nFragentitel: {$name}"
            . "\nFragentext: {$text}"
            . "\n\nAntworte NUR mit einer kommaseparierten Liste der Schlagworte."
            . " Keine Erklaerungen, keine Nummerierung, keine Anführungszeichen.";
    }

    /**
     * Calls the local Ollama API.
     *
     * @param string $prompt Prompt text.
     * @return string Raw model response.
     */
    private function call_ollama(string $prompt): string {
        $url   = rtrim($this->config['ai_ollama_url'] ?? 'http://localhost:11434', '/');
        $model = $this->config['ai_ollama_model'] ?? 'gemma3:4b';

        $payload = json_encode([
            'model'   => $model,
            'prompt'  => $prompt,
            'stream'  => false,
            'options' => ['temperature' => 0.2, 'num_predict' => 100],
        ]);

        $response = $this->http_post(
            $url . '/api/generate',
            $payload,
            ['Content-Type: application/json'],
            30
        );

        if ($response === false) {
            return '';
        }

        $data = json_decode($response, true);
        return $data['response'] ?? '';
    }

    /**
     * Calls the Anthropic Claude API.
     * Only anonymised question text is sent — no Moodle user data.
     *
     * @param string $prompt Prompt text.
     * @return string Raw model response.
     */
    private function call_claude(string $prompt): string {
        $apikey = $this->config['ai_claude_apikey'] ?? '';
        $model  = $this->config['ai_claude_model'] ?? 'claude-haiku-4-5-20251001';

        if (empty($apikey)) {
            return '';
        }

        $payload = json_encode([
            'model'      => $model,
            'max_tokens' => 150,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $response = $this->http_post(
            'https://api.anthropic.com/v1/messages',
            $payload,
            [
                'Content-Type: application/json',
                'x-api-key: ' . $apikey,
                'anthropic-version: 2023-06-01',
            ],
            30
        );

        if ($response === false) {
            return '';
        }

        $data = json_decode($response, true);
        return $data['content'][0]['text'] ?? '';
    }

    /**
     * Parses the raw AI response into a clean tag list.
     *
     * @param string $raw     Raw model output.
     * @param int    $maxtags Maximum number of tags to return.
     * @return string[]       Cleaned, deduplicated tag list.
     */
    private function parse_tags(string $raw, int $maxtags): array {
        if (empty(trim($raw))) {
            return [];
        }
        $tags = array_map('trim', explode(',', $raw));
        $tags = array_filter($tags, fn($t) => mb_strlen($t) >= 2 && mb_strlen($t) <= 50);
        $tags = array_values(array_unique($tags));
        return array_slice($tags, 0, $maxtags);
    }

    /**
     * Simple HTTP POST via Moodle's \curl wrapper.
     *
     * Using \curl (not native curl_*) ensures Moodle's proxy settings and
     * SSRF-protection rules are respected (moodle-curl-statt-moodle-curl).
     *
     * @param string   $url     Target URL.
     * @param string   $payload JSON payload.
     * @param string[] $headers HTTP headers as "Name: value" strings.
     * @param int      $timeout Timeout in seconds.
     * @return string|false     Response body or false on error.
     */
    private function http_post(string $url, string $payload, array $headers, int $timeout): string|false {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        // Convert "Name: value" header strings to the associative array
        // format expected by \curl::setHeader().
        $curl = new \curl(['ignoresecurity' => false]);
        $curl->setopt([
            'CURLOPT_TIMEOUT'    => $timeout,
            'CURLOPT_HTTPHEADER' => $headers,
        ]);

        // Passing a string to post() sets CURLOPT_POSTFIELDS directly,
        // preserving the raw JSON body and the Content-Type header we set above.
        $result = $curl->post($url, $payload);

        if ($curl->get_errno() !== 0) {
            return false;
        }
        return $result;
    }
}
