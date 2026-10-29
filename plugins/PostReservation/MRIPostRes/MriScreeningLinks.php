<?php

/**
 * Values stored in the screening and logbook custom attributes of a reservation.
 *
 * Attributes hold plain text or a plain URL. Templates escape the value and turn URLs into links on display,
 * so no HTML is stored.
 */
final class MriScreeningLinks
{
    /**
     * Survey token page of the participant, stored as the screening link.
     */
    public static function surveyTokenUrl(string $baseUrl, int $surveyId, string $token): string
    {
        return $baseUrl . '/' . $surveyId . '/token/' . $token;
    }

    /**
     * Survey token page as shown in the create-path notice (the URL includes index.php).
     */
    public static function indexedSurveyTokenUrl(string $baseUrl, int $surveyId, string $token): string
    {
        return $baseUrl . '/index.php/' . $surveyId . '/token/' . $token;
    }

    /**
     * Screening text once the participant has completed the screening.
     */
    public static function completedText(string $screeningText, string $completedText): string
    {
        return $screeningText . ' ' . $completedText;
    }

    /**
     * Screening text when the participant has not been screened.
     */
    public static function notCompletedText(string $screeningText, string $notCompletedText): string
    {
        return $screeningText . ' ' . $notCompletedText;
    }
}
