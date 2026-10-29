<?php

/**
 * Interprets LimeSurvey remote control responses.
 */
final class MriLimesurvey
{
    /**
     * True when add_participants refused a participant because its token already exists in the survey.
     *
     * LimeSurvey answers with the participants as they were added; a refused one carries an "errors" entry.
     *
     * @param mixed $response the value returned by add_participants
     */
    public static function isDuplicateTokenError(mixed $response, string $token): bool
    {
        if (!is_array($response)) {
            return false;
        }

        $message = sprintf('Token "%s" has already been taken.', $token);
        foreach ($response as $participant) {
            if (is_array($participant) && ($participant['errors']['token'][0] ?? null) === $message) {
                return true;
            }
        }

        return false;
    }
}
