<?php

class VidsrcExtractor
{
    private $baseHelperUrl = "https://9anime.eltik.net";

    public function matchRegex($pattern, $text)
    {
        $matches = [];
        preg_match($pattern, $text, $matches);
        $value = null;
        if (!empty($matches)) {
            $value = $matches[1];
        }
        return $value;
    }

    public function getVidsrc($externalId)
    {
        $dataIdResponse = file_get_contents("https://vidsrc.to/embed/movie/$externalId");
        $pattern = '/.*data-id="([^"]*)".*/';
        $dataId = $this->matchRegex($pattern, $dataIdResponse);

        if (!$dataId) {
            return null;
        }

        $vidplayIdResponse = file_get_contents("https://vidsrc.to/ajax/embed/episode/$dataId/sources");
        $pattern = '/"id":"([^"]*)".*"Vidplay/';
        $vidplayId = $this->matchRegex($pattern, $vidplayIdResponse);

        if (!$vidplayId) {
            return null;
        }

        $encryptedProviderUrlResponse = file_get_contents("https://vidsrc.to/ajax/embed/source/$vidplayId");
        $pattern = '/"url":"([^"]*)"/';
        $encryptedProviderUrl = $this->matchRegex($pattern, $encryptedProviderUrlResponse);

        if (!$encryptedProviderUrl) {
            return null;
        }

        $providerEmbedResponse = file_get_contents("$this->baseHelperUrl/fmovies-decrypt?query=$encryptedProviderUrl&apikey=jerry");
        $pattern = '/"url":"([^"]*)"/';
        $providerEmbed = $this->matchRegex($pattern, $providerEmbedResponse);

        if (!$providerEmbed) {
            return null;
        }

        $pattern = '/.*\/e\/([^\?]*)(\?.*)/';
        preg_match($pattern, $providerEmbed, $matches);
        $providerQuery = null;
        $params = null;
        if (count($matches) == 3) {
            $providerQuery = $matches[1];
            $params = $matches[2];
        }

        if (!$providerQuery || !$params) {
            return null;
        }

        $futoken = file_get_contents("https://vidstream.pro/futoken");

        if (!$futoken) {
            return null;
        }

        $data = [
            'query' => $providerQuery,
            'futoken' => $futoken,
        ];
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
            ],
        ];
        $context = stream_context_create($options);
        $rawUrlResponse = file_get_contents("$this->baseHelperUrl/rawvizcloud?query=$providerQuery&apikey=jerry", false, $context);

        $pattern = '/"rawURL":"([^"]*)"/';
        $rawUrl = $this->matchRegex($pattern, $rawUrlResponse);

        if (!$rawUrl) {
            return null;
        }

        $headers = [
            "Referer: $providerEmbed",
        ];
        $videoLinkResponse = file_get_contents("$rawUrl$params", false, stream_context_create(['http' => ['header' => $headers]]));
        $cdLink = str_replace('\/', '/', $videoLinkResponse);
        $pattern = '/"file":"([^"]*)"/';
        preg_match($pattern, $cdLink, $matches);
        $videoLink = null;
        if (count($matches) > 1) {
            $videoLink = $matches[1];
        }

        return $videoLink;
    }
}

$proxy = "http://localhost:8080/"; // Proxy Url

$extractor = new VidsrcExtractor();
// $url = $proxy.$extractor->getVidsrc("980489");
$url = $extractor->getVidsrc("980489");
echo $url;
?>
