<?php

declare(strict_types=1);

function handleAdminApi(): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $githubPath = githubCachePath();
        jsonResponse([
            'success' => true,
            'csrf' => csrfToken(),
            'mustChangePassword' => (bool) (authRecord()['mustChangePassword'] ?? false),
            'pens' => listPanelPens(),
            'siteInfo' => readJsonFile(IXPANEL_SITE_INFO_FILE, ['visitors' => []]),
            'github' => $githubPath ? readJsonFile($githubPath) : null,
            'githubPathFound' => $githubPath ? basename($githubPath) : null,
            'reviews' => listReviews(),
        ]);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
    }

    $body = requestBody();
    if (!hash_equals(csrfToken(), (string) ($body['csrf'] ?? ''))) {
        jsonResponse(['success' => false, 'error' => 'The security token expired. Refresh iXPanel.'], 419);
    }

    try {
        match ((string) ($body['action'] ?? '')) {
            'savePen' => savePanelPen($body),
            'deletePen' => deletePanelPen($body),
            'saveSiteInfo' => savePanelSiteInfo($body),
            'changePassword' => changePanelPassword($body),
            'setReviewStatus' => setPanelReviewStatus($body),
            'deleteReview' => deletePanelReview($body),
            default => throw new RuntimeException('Unknown administration action.'),
        };
    } catch (Throwable $error) {
        jsonResponse(['success' => false, 'error' => $error->getMessage()], 400);
    }
}

function setPanelReviewStatus(array $body): never
{
    updateReviewStatus(
        trim((string) ($body['id'] ?? '')),
        trim((string) ($body['status'] ?? ''))
    );
    jsonResponse(['success' => true, 'message' => 'Review status updated.']);
}

function deletePanelReview(array $body): never
{
    deleteReview(trim((string) ($body['id'] ?? '')));
    jsonResponse(['success' => true, 'message' => 'Review deleted.']);
}

function savePanelPen(array $body): never
{
    $id = trim((string) ($body['id'] ?? ''));
    $content = (string) ($body['content'] ?? '');

    if (!validPenId($id) || !is_file(penPath($id))) {
        throw new RuntimeException('The selected pen does not exist.');
    }
    if (strlen($content) > IXPANEL_MAX_PEN_SIZE) {
        throw new RuntimeException('Pen content exceeds the 500 KB limit.');
    }

    $pen = readJsonFile(penPath($id));
    $pen['content'] = $content;
    $pen['language'] = trim((string) ($body['language'] ?? 'plaintext')) ?: 'plaintext';
    $pen['updated'] = gmdate('c');
    atomicJsonWrite(penPath($id), $pen);
    jsonResponse(['success' => true, 'message' => 'Pen saved.']);
}

function deletePanelPen(array $body): never
{
    $id = trim((string) ($body['id'] ?? ''));
    if (!validPenId($id) || !is_file(penPath($id)) || !unlink(penPath($id))) {
        throw new RuntimeException('The pen could not be deleted.');
    }

    jsonResponse(['success' => true, 'message' => 'Pen deleted.']);
}

function savePanelSiteInfo(array $body): never
{
    $siteInfo = $body['siteInfo'] ?? null;
    if (!is_array($siteInfo)) {
        throw new RuntimeException('siteInfo.json must contain a JSON object.');
    }

    atomicJsonWrite(IXPANEL_SITE_INFO_FILE, $siteInfo);
    jsonResponse(['success' => true, 'message' => 'siteInfo.json saved.']);
}

function changePanelPassword(array $body): never
{
    $password = (string) ($body['password'] ?? '');
    if (strlen($password) < 12) {
        throw new RuntimeException('Use a password at least 12 characters long.');
    }

    $newHash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($newHash) || !password_verify($password, $newHash)) {
        throw new RuntimeException('PHP could not generate a verifiable password hash.');
    }

    $auth = authRecord();
    $auth['passwordHash'] = $newHash;
    $auth['mustChangePassword'] = false;
    $auth['updatedAt'] = gmdate('c');
    $auth['passwordChangedAt'] = gmdate('c');
    atomicJsonWrite(IXPANEL_AUTH_FILE, $auth);

    jsonResponse(['success' => true, 'message' => 'Administrator password updated.']);
}
