<?php
/**
 * GHARBETI COMPONENTS LIBRARY
 * Reusable UI components for consistent design.
 */

if (!function_exists('renderButton')) {
    function renderButton($text, $type = 'primary', $attributes = []) {
        $class = 'btn btn-' . preg_replace('/[^a-z0-9_-]/i', '', (string) $type);
        if (!empty($attributes['class'])) {
            $class .= ' ' . $attributes['class'];
        }

        $href = $attributes['href'] ?? null;
        $id = !empty($attributes['id']) ? ' id="' . htmlspecialchars($attributes['id']) . '"' : '';
        $disabled = !empty($attributes['disabled']) ? ' disabled' : '';
        $onclick = !empty($attributes['onclick']) ? ' onclick="' . htmlspecialchars($attributes['onclick']) . '"' : '';
        $extra = !empty($attributes['extra']) ? ' ' . trim($attributes['extra']) : '';
        $text = (string) $text;

        if ($href) {
            return '<a href="' . htmlspecialchars($href) . '" class="' . htmlspecialchars($class) . '"' . $id . $onclick . $extra . '>' . $text . '</a>';
        }

        $buttonType = htmlspecialchars($attributes['button_type'] ?? 'button');
        return '<button type="' . $buttonType . '" class="' . htmlspecialchars($class) . '"' . $id . $onclick . $disabled . $extra . '>' . $text . '</button>';
    }
}

if (!function_exists('renderButtonGroup')) {
    function renderButtonGroup($buttons, $vertical = false) {
        $class = 'btn-group' . ($vertical ? ' btn-group-vertical' : '');
        $html = '<div class="' . $class . '">';
        foreach ($buttons as $button) {
            $html .= renderButton($button['text'] ?? '', $button['type'] ?? 'primary', $button['attributes'] ?? []);
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('renderCard')) {
    function renderCard($content, $header = '', $footer = '', $attributes = []) {
        $class = 'card';
        if (!empty($attributes['class'])) {
            $class .= ' ' . $attributes['class'];
        }
        if (!empty($attributes['hover'])) {
            $class .= ' card-hover';
        }

        $html = '<div class="' . htmlspecialchars($class) . '">';
        if ($header !== '') {
            $html .= '<div class="card-header">' . $header . '</div>';
        }
        $html .= '<div class="card-body">' . $content . '</div>';
        if ($footer !== '') {
            $html .= '<div class="card-footer">' . $footer . '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('getTrustScoreClass')) {
    function getTrustScoreClass($score) {
        $score = (int) $score;
        if ($score >= 80) {
            return 'trust-high';
        }
        if ($score >= 50) {
            return 'trust-medium';
        }
        return 'trust-low';
    }
}

if (!function_exists('renderRoomCard')) {
    function renderRoomCard($room) {
        $roomId = (int) ($room['id'] ?? 0);
        $trustClass = getTrustScoreClass($room['trust_score'] ?? 0);
        $isFavorited = function_exists('isLoggedIn') && isLoggedIn() && function_exists('isFavorited')
            ? isFavorited(getCurrentUserId(), $roomId)
            : false;

        ob_start();
        ?>
        <div class="room-card" onclick="window.location.href='<?php echo SITE_URL; ?>/pages/room-detail.php?id=<?php echo $roomId; ?>'">
            <div class="card-image">
                <img src="<?php echo htmlspecialchars(getRoomImageUrl($room['primary_image'] ?? '')); ?>"
                     alt="<?php echo htmlspecialchars($room['title'] ?? 'Room'); ?>"
                     loading="lazy">
                <?php if (!empty($room['is_verified'])): ?>
                    <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                <?php endif; ?>
                <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                    <button class="wishlist-btn <?php echo $isFavorited ? 'active' : ''; ?>"
                            type="button"
                            onclick="event.stopPropagation(); if (typeof toggleFavorite === 'function') { toggleFavorite(<?php echo $roomId; ?>); }">
                        <i class="<?php echo $isFavorited ? 'fas' : 'far'; ?> fa-heart"></i>
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-content">
                <div class="card-price">NPR <?php echo number_format((float) ($room['price'] ?? 0)); ?><span>/month</span></div>
                <h3 class="card-title"><?php echo htmlspecialchars($room['title'] ?? 'Untitled Room'); ?></h3>
                <div class="card-location"><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($room['location'] ?? 'Unknown location'); ?></div>
                <div class="landlord-info">
                    <img src="<?php echo htmlspecialchars(getAvatarUrl($room['landlord_avatar'] ?? '')); ?>"
                         alt="<?php echo htmlspecialchars($room['landlord_name'] ?? 'Landlord'); ?>"
                         class="landlord-avatar">
                    <span class="landlord-name"><?php echo htmlspecialchars($room['landlord_name'] ?? 'Unknown'); ?></span>
                    <span class="trust-score <?php echo $trustClass; ?>"><?php echo (int) ($room['trust_score'] ?? 0); ?></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('renderTestimonialCard')) {
    function renderTestimonialCard($testimonial) {
        $rating = max(0, min(5, (int) round($testimonial['rating_overall'] ?? 5)));
        ob_start();
        ?>
        <div class="testimonial-card">
            <div class="testimonial-rating">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star<?php echo $i <= $rating ? '' : '-o'; ?>"></i>
                <?php endfor; ?>
            </div>
            <p class="testimonial-text">"<?php echo htmlspecialchars(substr((string) ($testimonial['review_text'] ?? ''), 0, 150)); ?><?php echo strlen((string) ($testimonial['review_text'] ?? '')) > 150 ? '...' : ''; ?>"</p>
            <div class="testimonial-author">
                <img src="<?php echo htmlspecialchars(getAvatarUrl($testimonial['reviewer_avatar'] ?? '')); ?>"
                     alt="<?php echo htmlspecialchars($testimonial['reviewer_name'] ?? 'Anonymous'); ?>"
                     class="testimonial-avatar">
                <div>
                    <h4><?php echo htmlspecialchars($testimonial['reviewer_name'] ?? 'Anonymous'); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($testimonial['room_title'] ?? ''); ?></p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('renderAlert')) {
    function renderAlert($message, $type = 'success', $dismissible = true) {
        $close = $dismissible ? '<button type="button" class="notification-close" onclick="this.parentElement.remove()">&times;</button>' : '';
        return '<div class="alert alert-' . htmlspecialchars($type) . '">' . $close . '<span>' . $message . '</span></div>';
    }
}

if (!function_exists('renderValidationErrors')) {
    function renderValidationErrors($errors) {
        if (empty($errors)) {
            return '';
        }
        $html = '<div class="alert alert-error"><div><strong>Please fix the following errors:</strong><ul>';
        foreach ($errors as $error) {
            $html .= '<li>' . htmlspecialchars((string) $error) . '</li>';
        }
        $html .= '</ul></div></div>';
        return $html;
    }
}

if (!function_exists('renderInput')) {
    function renderInput($type, $name, $label = '', $value = '', $attributes = []) {
        $required = !empty($attributes['required']) ? ' required' : '';
        $placeholder = isset($attributes['placeholder']) ? ' placeholder="' . htmlspecialchars($attributes['placeholder']) . '"' : '';
        $class = 'form-control' . (!empty($attributes['class']) ? ' ' . $attributes['class'] : '');
        $id = $attributes['id'] ?? $name;
        $disabled = !empty($attributes['disabled']) ? ' disabled' : '';

        if ($type === 'checkbox') {
            $checked = !empty($value) ? ' checked' : '';
            $html = '<div class="form-check">';
            $html .= '<input type="checkbox" class="form-check-input" id="' . htmlspecialchars($id) . '" name="' . htmlspecialchars($name) . '"' . $checked . $required . $disabled . '>';
            if ($label !== '') {
                $html .= '<label class="form-check-label" for="' . htmlspecialchars($id) . '">' . htmlspecialchars($label) . '</label>';
            }
            $html .= '</div>';
            return $html;
        }

        $html = '<div class="form-group">';
        if ($label !== '') {
            $html .= '<label class="form-label" for="' . htmlspecialchars($id) . '">';
            if (!empty($attributes['icon'])) {
                $html .= '<i class="fas fa-' . htmlspecialchars($attributes['icon']) . '"></i> ';
            }
            $html .= htmlspecialchars($label) . '</label>';
        }

        if ($type === 'textarea') {
            $rows = (int) ($attributes['rows'] ?? 3);
            $html .= '<textarea class="' . htmlspecialchars($class) . '" id="' . htmlspecialchars($id) . '" name="' . htmlspecialchars($name) . '" rows="' . $rows . '"' . $placeholder . $required . $disabled . '>' . htmlspecialchars((string) $value) . '</textarea>';
        } elseif ($type === 'select') {
            $html .= '<select class="' . htmlspecialchars($class) . '" id="' . htmlspecialchars($id) . '" name="' . htmlspecialchars($name) . '"' . $required . $disabled . '>';
            foreach (($attributes['options'] ?? []) as $optionValue => $optionLabel) {
                $selected = ((string) $value === (string) $optionValue) ? ' selected' : '';
                $html .= '<option value="' . htmlspecialchars((string) $optionValue) . '"' . $selected . '>' . htmlspecialchars((string) $optionLabel) . '</option>';
            }
            $html .= '</select>';
        } else {
            $html .= '<input type="' . htmlspecialchars($type) . '" class="' . htmlspecialchars($class) . '" id="' . htmlspecialchars($id) . '" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars((string) $value) . '"' . $placeholder . $required . $disabled . '>';
        }

        if (!empty($attributes['help'])) {
            $html .= '<small class="form-text">' . htmlspecialchars($attributes['help']) . '</small>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('renderForm')) {
    function renderForm($fields, $submitText = 'Submit', $attributes = []) {
        $method = htmlspecialchars($attributes['method'] ?? 'POST');
        $action = htmlspecialchars($attributes['action'] ?? '');
        $class = !empty($attributes['class']) ? ' class="' . htmlspecialchars($attributes['class']) . '"' : '';
        $enctype = !empty($attributes['file_upload']) ? ' enctype="multipart/form-data"' : '';

        $html = '<form method="' . $method . '" action="' . $action . '"' . $class . $enctype . '>';
        if (function_exists('generateCSRFToken')) {
            $html .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
        }
        foreach ($fields as $field) {
            $html .= renderInput($field['type'], $field['name'], $field['label'] ?? '', $field['value'] ?? '', $field['attributes'] ?? []);
        }
        $html .= '<div class="form-group"><button type="submit" class="btn btn-primary">' . htmlspecialchars($submitText) . '</button></div>';
        $html .= '</form>';
        return $html;
    }
}

if (!function_exists('renderTable')) {
    function renderTable($headers, $rows, $attributes = []) {
        $class = 'table' . (!empty($attributes['class']) ? ' ' . $attributes['class'] : '');
        $html = !empty($attributes['responsive']) ? '<div class="table-responsive">' : '';
        $html .= '<table class="' . htmlspecialchars($class) . '"><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars((string) $header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . $cell . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        if (!empty($attributes['responsive'])) {
            $html .= '</div>';
        }
        return $html;
    }
}

if (!function_exists('renderModal')) {
    function renderModal($id, $title, $content, $footer = '', $size = 'md') {
        $sizes = ['sm' => '400px', 'md' => '500px', 'lg' => '700px', 'xl' => '900px'];
        $width = $sizes[$size] ?? $sizes['md'];
        ob_start();
        ?>
        <div id="<?php echo htmlspecialchars($id); ?>" class="modal" aria-hidden="true">
            <div class="modal-content" style="max-width: <?php echo htmlspecialchars($width); ?>;">
                <div class="modal-header">
                    <h3><?php echo htmlspecialchars($title); ?></h3>
                    <button type="button" class="modal-close" data-close-modal="<?php echo htmlspecialchars($id); ?>">&times;</button>
                </div>
                <div class="modal-body"><?php echo $content; ?></div>
                <?php if ($footer !== ''): ?>
                    <div class="modal-footer"><?php echo $footer; ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('renderBadge')) {
    function renderBadge($text, $type = 'primary') {
        return '<span class="badge badge-' . htmlspecialchars($type) . '">' . htmlspecialchars((string) $text) . '</span>';
    }
}

if (!function_exists('renderTrustBadge')) {
    function renderTrustBadge($score) {
        $score = (int) $score;
        $type = $score >= 80 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
        return renderBadge('Trust: ' . $score, $type);
    }
}

if (!function_exists('renderVerificationBadges')) {
    function renderVerificationBadges($user) {
        $badges = [];
        if (!empty($user['email_verified'])) {
            $badges[] = '<span class="badge badge-info" title="Email Verified"><i class="fas fa-envelope"></i></span>';
        }
        if (!empty($user['phone_verified'])) {
            $badges[] = '<span class="badge badge-success" title="Phone Verified"><i class="fas fa-phone"></i></span>';
        }
        if (!empty($user['id_verified'])) {
            $badges[] = '<span class="badge badge-warning" title="ID Verified"><i class="fas fa-id-card"></i></span>';
        }
        return implode(' ', $badges);
    }
}

if (!function_exists('renderPagination')) {
    function renderPagination($currentPage, $totalPages, $urlPattern) {
        $currentPage = (int) $currentPage;
        $totalPages = (int) $totalPages;
        if ($totalPages <= 1) {
            return '';
        }

        $html = '<div class="pagination">';
        if ($currentPage > 1) {
            $html .= '<a href="' . htmlspecialchars(str_replace('{page}', (string) ($currentPage - 1), $urlPattern)) . '" class="page-link"><i class="fas fa-chevron-left"></i></a>';
        }
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $currentPage ? ' active' : '';
            $html .= '<a href="' . htmlspecialchars(str_replace('{page}', (string) $i, $urlPattern)) . '" class="page-link' . $active . '">' . $i . '</a>';
        }
        if ($currentPage < $totalPages) {
            $html .= '<a href="' . htmlspecialchars(str_replace('{page}', (string) ($currentPage + 1), $urlPattern)) . '" class="page-link"><i class="fas fa-chevron-right"></i></a>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('renderSpinner')) {
    function renderSpinner($text = 'Loading...') {
        return '<div class="spinner"><div class="spinner-border"></div><p>' . htmlspecialchars((string) $text) . '</p></div>';
    }
}

if (!function_exists('renderSkeleton')) {
    function renderSkeleton($type = 'card', $count = 1) {
        $allowed = ['card', 'text', 'avatar', 'title'];
        $type = in_array($type, $allowed, true) ? $type : 'card';
        $html = '';
        for ($i = 0; $i < (int) $count; $i++) {
            $html .= '<div class="skeleton-' . $type . '"></div>';
        }
        return $html;
    }
}

if (!function_exists('renderEmptyState')) {
    function renderEmptyState($message, $icon = 'inbox', $buttonText = '', $buttonLink = '') {
        $html = '<div class="empty-state">';
        $html .= '<i class="fas fa-' . htmlspecialchars($icon) . '"></i>';
        $html .= '<h3>' . htmlspecialchars((string) $message) . '</h3>';
        if ($buttonText !== '' && $buttonLink !== '') {
            $html .= '<a href="' . htmlspecialchars($buttonLink) . '" class="btn btn-primary">' . htmlspecialchars((string) $buttonText) . '</a>';
        }
        $html .= '</div>';
        return $html;
    }
}
?>
