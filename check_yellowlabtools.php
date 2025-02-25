<?php declare (strict_types=1);

/**
 * I am the Request for the Yellowlabtools API.
 */
class YellowLabToolsRequest {
    public string $device = 'desktop';
    public bool $screenshot = false;
    public string $url = '';
    public bool $waitForResponse = true;
}

/**
 * I am the tentative Yellowlabtools API client.
 */
class YellowLabTools {
    // I am the request that has been sent.
    private YellowLabToolsRequest $request;
    // I am the response object that has been received.
    private array $response;
    // I am the minimum score that has been found.
    private int $minScore = 100;

    private function __construct(YellowLabToolsRequest $request, array $response) {
        $this->request = $request;
        $this->response = $response;
    }

    public static function fetch(YellowLabToolsRequest $request): self {
        $curl = curl_init('https://yellowlabtools.nox.kiwi/api/runs');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request));
        curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($request)),
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        if (!$response) {
            printf("cUrl error (#%d): %s<br>\n",
                curl_errno($curl),
                htmlspecialchars(curl_error($curl))
            );
        }

        return new self($request, json_decode($response, true, 512, JSON_THROW_ON_ERROR));
    }

    public function output(): void {
        $val = 'FINISHED|';

        // This is too much data. Maybe add a whitelist for metrics to actually add?
        foreach ($this->response['rules'] as $name => $rule) {
            $name = ucfirst($name);
            $val .= " 'rule{$name}Score'={$rule['score']}p;75;50;0;100";
            $val .= " 'rule{$name}Value'={$rule['value']}";
        }

        foreach ($this->response['scoreProfiles']['generic']['categories'] as $name => $category) {
            $name = ucfirst($name);
//          $this->compareScore((int)$category['categoryScore']);
            $val .= " 'category{$name}'={$category['categoryScore']}p;75;50;0;100";
        }

        $val .= " 'globalScore'={$this->response['scoreProfiles']['generic']['globalScore']}p;75;50;0;100";
        $this->compareScore((int)$this->response['scoreProfiles']['generic']['globalScore']);

        echo $val;

        $this->terminate();
    }

    /**
     * I will return from the process with the correct nagios exit code.
     * @return void
     */
    public function terminate(): void {
        if ($this->minScore > 75) {
            exit(0);
        }
        if ($this->minScore > 50) {
            exit(1);
        }
        exit(2);
    }

    private function compareScore(int $score): void {
        $this->minScore = min($this->minScore, $score);
    }
}


// BUILD REQUEST
$request = new YellowLabToolsRequest();

$request->url = $argv[1] ?? 'https://jan.nox.kiwi';

// PROCESS REQUEST
$ylt = YellowLabTools::fetch($request);


// OUTPUT
$ylt->output();

die('You cant see me.');
