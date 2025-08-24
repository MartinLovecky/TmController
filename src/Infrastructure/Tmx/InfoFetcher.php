<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Infrastructure\Tmx;

use Yuhzel\TmController\App\Service\Aseco;
use Yuhzel\TmController\Services\HttpClient;

class InfoFetcher
{
    public string $section = '';
    public string $prefix = 'tmnforever';
    public string $uid = '';
    public int $id = 0;
    public bool $records = false;
    public string $error = '';
    public string $name = '';
    public int $userid = 0;
    public string $author = '';
    public string $uploaded = '';
    public string $updated = '';
    public bool $visible = true;
    public string $type = '';
    public string $envir = '';
    public string $mood = '';
    public string $style = '';
    public string $routes = '';
    public string $length = '';
    public string $diffic = '';
    public int $lbrating = 0;
    public mixed $awards = null;
    public mixed $comments = null;
    public bool $custimg = false;
    public string $game = '';
    public string $acomment = '';
    public string $pageurl = '';
    public int $replayid = 0;
    public string $replayurl  = '';
    public string $imageurl = '';
    public string $thumburl = '';
    public string $dloadurl = '';
    public array $recordlist = [];


    public function setData(string $uid, bool $records): void
    {
        $this->uid = $uid;
        $this->records = $records;
        $this->getData(true);
    }

    private function fetchApiData(string $action, array $params): ?string
    {
        $endpoint = "http://{$this->prefix}.tm-exchange.com/apiget.aspx";

        $httpClient = new HttpClient();

        $params['action'] = $action;

        $response = $httpClient->get($endpoint, $params);

        if (!$response) {
            return null;
        }

        if (strpos($response, chr(27)) !== false) {
            $this->handleError("Cannot decode data for action: {$action}");
            return null;
        }

        return $response;
    }

    private function handleError(string $message): void
    {
        $this->error = $message;
        Aseco::console($this->error);
    }

    private function getData(bool $isUid): void
    {
        $params = [
            $isUid ? 'uid' : 'id' => $isUid ? $this->uid : $this->id,
        ];

        $response = $this->fetchApiData('apitrackinfo', $params);

        if ($response === null) {
            return;
        }

        $fields = explode(chr(9), $response);
        $this->populateFields($fields, $isUid);
        $this->fetchMiscTrackInfo();

        if ($this->records) {
            $this->fetchTrackRecords();
        }
    }

    private function populateFields(array $fields, bool $isUid): void
    {
        $fields = $this->convertFields($fields);

        if ($isUid) {
            [
                $this->id,
                $this->name,
                $this->userid,
                $this->author,
                $this->uploaded,
                $this->updated,
                $this->visible,
                $this->type,
                $this->envir,
                $this->mood,
                $this->style,
                $this->routes,
                $this->length,
                $this->diffic,
                $this->lbrating,
                $this->game,
                $comment
            ] = $fields;

            $this->acomment = $this->formatComment($comment);
            $this->pageurl  = "http://{$this->prefix}.tm-exchange.com/main.aspx?action=trackshow&id={$this->id}";
            $this->imageurl = "http://{$this->prefix}.tm-exchange.com/get.aspx?action=trackscreen&id={$this->id}";
            $this->thumburl = "http://{$this->prefix}.tm-exchange.com/get.aspx?action=trackscreensmall&id={$this->id}";
            $this->dloadurl = "http://{$this->prefix}.tm-exchange.com/get.aspx?action=trackgbx&id={$this->id}";
        }
    }

    private function fetchMiscTrackInfo(): void
    {
        $response = $this->fetchApiData('apisearch', ['trackid' => $this->id]);

        if ($response === false || strpos($response, chr(27)) !== false) {
            return;
        }

        $fields = explode(chr(9), $response);
        $fields = $this->convertFields($fields);

        $this->awards = $fields[12];
        $this->comments = $fields[13];
        $this->replayid = empty($fields[16]) ? 0 : $fields[16];

        if ($this->replayid > 0) {
            $this->replayurl = "http://{$this->prefix}.tm-exchange.com/get.aspx?action=recordgbx&id={$this->replayid}";
        }
    }

    private function fetchTrackRecords(): void
    {
        $response = $this->fetchApiData('apitrackrecords', ['id' => $this->id]);

        if ($response === null) {
            return;
        }

        $lines = explode("\r\n", $response);

        foreach (array_slice($lines, 0, 10) as $index => $line) {
            if ($line === '') {
                break;
            }

            $fields = explode(chr(9), $line);
            $fields = $this->convertFields($fields);

            $this->recordlist[$index] = [
                'replayid'  => $fields[0],
                'userid'    => $fields[1],
                'name'      => $fields[2] ?? '',
                'time'      => $fields[3],
                'replayat'  => $fields[4] ?? '',
                'trackat'   => $fields[5] ?? '',
                'approved'  => $fields[6],
                'score'     => $fields[7],
                'expires'   => $fields[8],
                'lockspan'  => $fields[9],
                'replayurl' => "http://{$this->prefix}.tm-exchange.com/get.aspx?action=recordgbx&id={$fields[0]}",
            ];
        }
    }

    private function convertFields(mixed $fields): array
    {
        return array_map(function ($value) {
            if ($value === "1" || $value === "0") {
                return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            } elseif (is_numeric($value) && ctype_digit($value)) {
                return (int) $value;
            }
            return $value;
        }, $fields);
    }

    private function formatComment(string $comment): string
    {
        $search = [chr(31), '[b]', '[/b]', '[i]', '[/i]', '[u]', '[/u]', '[url]', '[/url]'];
        $replace = ['<br/>', '<b>', '</b>', '<i>', '</i>', '<u>', '</u>', '<i>', '</i>'];
        $formatted = str_ireplace($search, $replace, $comment);
        return preg_replace('/\[url=".*"\]/', '<i>', $formatted) ?? $formatted;
    }
}
