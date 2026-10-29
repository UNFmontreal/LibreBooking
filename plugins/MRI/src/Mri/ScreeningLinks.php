<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

/**
 * Values stored in the screening custom attribute of a reservation.
 *
 * Attributes hold plain text or a plain URL. Templates escape the value and turn URLs into links on display,
 * so no HTML is stored.
 */
final class ScreeningLinks
{
    /**
     * Survey token page of the participant, stored as the screening link while the screening is open.
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
     * The answers of the screening in the LimeSurvey data entry, stored as the screening link once it is completed.
     */
    public static function responseUrl(string $baseUrl, int $surveyId, int $responseId): string
    {
        return self::responseUrlPrefix($baseUrl, $surveyId) . $responseId;
    }

    /**
     * Screening text once the participant has completed the screening, when there is no response to link to.
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

    /**
     * Whether a stored screening link marks the screening as completed: a link to its answers, or the completed text.
     */
    public static function isCompleted(string $link, string $completedText, string $baseUrl, int $surveyId): bool
    {
        return $link === $completedText || str_starts_with($link, self::responseUrlPrefix($baseUrl, $surveyId));
    }

    private static function responseUrlPrefix(string $baseUrl, int $surveyId): string
    {
        return $baseUrl . '/index.php/admin/dataentry/sa/editdata/subaction/edit/surveyid/' . $surveyId . '/id/';
    }
}
