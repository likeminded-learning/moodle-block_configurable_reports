<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * GitHub helper class
 *
 * @copyright  2020 Juan Leyva <juan@moodle.com>
 * @package    block_configurable_reports
 * @author     Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports;

/**
 * Class github
 *
 * @package   block_configurable_reports
 * @author    Juan leyva <http://www.twitter.com/jleyvadelgado>
 */
class github extends \curl {

    /** @var int HTTP status of a successful request. */
    const HTTP_OK = 200;

    /** @var int HTTP status of a file that does not exist. */
    const HTTP_NOT_FOUND = 404;

    /**
     * @var string
     */
    protected string $repo = '';

    /**
     * Set repository
     *
     * @param string $repo
     * @return void
     */
    public function set_repo($repo) {
        $this->repo = $repo;
        $this->setHeader('Accept: application/vnd.github+json');
    }

    /**
     * Set a basic auth header.
     *
     * @param string $username The username to use.
     * @param string $password The password to use.
     */
    public function set_basic_auth(string $username, string $password): bool {
        $value = 'Basic ' . base64_encode($username . ':' . $password);
        $this->setHeader('Authorization:' . $value);

        return true;
    }

    /**
     * Get
     *
     * @param string $url
     * @param array $params
     * @param array $options
     * @return bool|string
     */
    public function get($url, $params = [], $options = []) {
        $repolink = 'https://api.github.com/repos/';
        $repolink .= $this->repo;
        $repolink .= $url;

        // Always verify the GitHub certificate, core curl does not do it by default.
        $options['CURLOPT_SSL_VERIFYPEER'] = true;

        return parent::get($repolink, $params, $options);
    }

    /**
     * Use a token for the requests, so that private repositories can be read.
     *
     * @param string $token A GitHub token with read access to the contents of the repository.
     * @return void
     */
    public function set_token(string $token): void {
        $this->setHeader('Authorization: Bearer ' . $token);
    }

    /**
     * Get the paths of all the files and folders in the repository.
     *
     * @param string|null $ref The branch, tag or commit. Null for the default branch.
     * @return \stdClass[] The tree entries, each with a path and a type ('blob' or 'tree').
     */
    public function get_tree(?string $ref): array {
        $response = $this->get_json('/git/trees/' . rawurlencode($ref ?? 'HEAD'), ['recursive' => 1]);

        return $response->tree ?? [];
    }

    /**
     * Get the contents of a file.
     *
     * @param string $path The path of the file in the repository.
     * @param string|null $ref The branch, tag or commit. Null for the default branch.
     * @return string|null The contents, or null when the file does not exist.
     */
    public function get_contents(string $path, ?string $ref): ?string {
        $params = $ref === null ? [] : ['ref' => $ref];
        $path = implode('/', array_map('rawurlencode', explode('/', $path)));
        $response = $this->get_json('/contents/' . $path, $params, [self::HTTP_NOT_FOUND]);
        if ($response === null || ($response->type ?? '') !== 'file') {
            return null;
        }

        return base64_decode($response->content ?? '');
    }

    /**
     * Get a JSON response of the GitHub API.
     *
     * @param string $url The URL in the repository.
     * @param array $params
     * @param int[] $nullcodes The HTTP status codes that give null instead of an error.
     * @return \stdClass|null
     * @throws \moodle_exception When the request fails.
     */
    protected function get_json(string $url, array $params = [], array $nullcodes = []): ?\stdClass {
        $response = $this->get($url, $params);
        $status = (int) ($this->get_info()['http_code'] ?? 0);

        if (in_array($status, $nullcodes, true)) {
            return null;
        }
        $data = json_decode((string) $response);
        if ($status !== self::HTTP_OK || !$data instanceof \stdClass) {
            throw new \moodle_exception('errorrepository', 'block_configurable_reports', '',
                $this->repo . ': HTTP ' . $status . ' ' . ($data->message ?? $this->get_errno()));
        }

        return $data;
    }

}
