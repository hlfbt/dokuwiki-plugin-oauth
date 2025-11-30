<?php

namespace dokuwiki\plugin\oauth;

/**
 * Manages extra user data stored in a separate file.
 * This data is typically used by the OAuth plugin to store provider-specific user information.
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Alexander Schulz <alex@schulz.foo>
 */
class UserExtrasManager
{
    /** The key used to store extra user data in the userdata array. */
    public const USER_EXTRAS_KEY = 'extras';

    protected string $file;

    public function __construct()
    {
        global $conf;
        $this->file = $conf['savedir'] . '/users.extras.php';
    }

    private function getFileHeader() : string
    {
        $header = "# users.extras.php\n";
        $header .= "# <?php exit()?>\n";
        $header .= "#\n";
        $header .= "# extra user data storage\n";
        $header .= "#\n";
        $header .= "# Don't edit this file manually, it is managed by the oauth plugin.\n";
        $header .= "#\n";
        $header .= "# Format:\n";
        $header .= "# user:json_encoded_data\n\n";

        return $header;
    }

    private function checkAndCreateFile() : bool
    {
        if (file_exists($this->file)) {
            return true;
        }

        return io_saveFile($this->file, $this->getFileHeader());
    }

    /**
     * Extract the extra data from the given user data array.
     *
     * @param array $userdata The user data array.
     * @param bool $unsetExtrasFields Whether to unset the extra fields from the user data array.
     * @return array The extra user fields.
     */
    public function extractExtras(array &$userdata, bool $unsetExtrasFields = false) : array
    {
        $extras = [];

        if (isset($userdata[self::USER_EXTRAS_KEY])) {
            $extras = $userdata[self::USER_EXTRAS_KEY];
            if ($unsetExtrasFields) {
                unset($userdata[self::USER_EXTRAS_KEY]);
            }
        }

        return $extras;
    }

    /**
     * Get the extra data for a single user
     *
     * @param string $user The username.
     * @return array The extra user fields.
     */
    public function getUserExtras(string $user) : array
    {
        $this->checkAndCreateFile();

        $lines = file($this->file);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) < 2) {
                continue;
            }
            [$u, $json] = $parts;
            if ($u === $user) {
                return json_decode($json, true);
            }
        }

        return [];
    }

    /**
     * Get all extra data for all users.
     *
     * @return array An associative array where keys are usernames and values are their extra data.
     */
    public function getAllUserExtras() : array
    {
        $this->checkAndCreateFile();

        $users = [];
        $lines = file($this->file);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) < 2) {
                continue;
            }
            [$user, $json] = $parts;
            $users[$user] = json_decode($json, true);
        }

        return $users;
    }

    /**
     * Merge extra data into an array of user data.
     *
     * @param array $users An associative array of user data, where keys are usernames.
     */
    public function mergeAllUsersExtras(array &$users)
    {
        $extraUsers = $this->getAllUserExtras();
        foreach ($extraUsers as $user => $extra) {
            if (isset($users[$user])) {
                $users[$user][self::USER_EXTRAS_KEY] = $extra;
            }
        }
    }

    /**
     * Save all user extras to the file.
     *
     * @param array $users An associative array where keys are usernames and values are their user data, including the 'extras' key.
     * @return bool True if the operation was successful, false otherwise.
     */
    public function saveAllUserExtras(array $users) : bool
    {
        $this->checkAndCreateFile();

        $lines = [];
        foreach ($users as $user => $userdata) {
            $extras = $this->extractExtras($userdata);
            $lines[] = $user . ':' . json_encode($extras);
        }

        return io_saveFile($this->file, $this->getFileHeader() . implode("\n", $lines) . "\n");
    }

    /**
     * Delete the extra data for a single user.
     *
     * @param string $user The username.
     * @return bool True if the operation was successful, false otherwise.
     */
    public function deleteUserExtras(string $user) : bool
    {
        $this->checkAndCreateFile();

        return io_deleteFromFile($this->file, '/^' . preg_quote($user, '/') . ':/', true);
    }

    /**
     * Delete the extra data for multiple users.
     *
     * @param array $users An array of usernames.
     * @return bool True if the operation was successful, false otherwise.
     */
    public function deleteUsersExtras(array $users) : bool
    {
        $this->checkAndCreateFile();

        $deleted = [];
        foreach ($users as $user) {
            $deleted[] = preg_quote($user, '/');
        }

        return io_deleteFromFile($this->file, '/^(' . implode('|', $deleted) . '):/', true);
    }

    /**
     * Save the extra data for a single user.
     *
     * @param string $user The username.
     * @param array $userdata The user data array, including the 'extras' key.
     * @param bool $replaceExisting Whether to replace existing extra data or merge it.
     * @return bool True if the operation was successful, false otherwise.
     */
    public function saveUserExtras(string $user, array &$userdata, bool $replaceExisting = true) : bool
    {
        $extras = $this->extractExtras($userdata);
        if (empty($extras)) {
            return true;
        }

        $this->checkAndCreateFile();
        $users = $this->getAllUserExtras();

        if (isset($users[$user])) {
            // update
            if ($replaceExisting) {
                $line = $user . ':' . json_encode($extras);
            } else {
                $line = $user . ':' . json_encode(array_merge($users[$user], $extras));
            }

            return io_replaceInFile($this->file, '/^' . preg_quote($user, '/') . ':/', $line, true);
        } else {
            // create
            $line = $user . ':' . json_encode($extras) . "\n";

            return io_saveFile($this->file, $line, true);
        }
    }
}
