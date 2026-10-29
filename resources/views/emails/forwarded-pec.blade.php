<div style="font-family: Arial, sans-serif; font-size: 14px; color: #1f2937;">
    <p style="margin:0 0 12px;">
        <strong>UnicoPEC</strong> ti inoltra questa {{ $email->mailAccount?->is_pec ? 'PEC' : 'email' }}
        perché è stata assegnata a te{{ $role ? " (ruolo: {$role})" : '' }}.
    </p>

    @if ($banner)
        <p style="margin:0 0 12px; padding:8px 12px; background:#fef3c7; border:1px solid #f59e0b;">{{ $banner }}</p>
    @endif

    <table style="border-collapse: collapse; margin-bottom: 16px;">
        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">Da</td><td>{{ $email->from_name }} &lt;{{ $email->from_email }}&gt;</td></tr>
        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">Oggetto</td><td>{{ $email->subject }}</td></tr>
        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">Ricevuta il</td><td>{{ $email->received_at?->format('d/m/Y H:i') }}</td></tr>
        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">Casella</td><td>{{ $email->mailAccount?->email_address }}</td></tr>
        @if ($category)
            <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">Classificazione</td><td>{{ $category }}</td></tr>
        @endif
        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">Allegati</td><td>{{ $attachmentsNote }}</td></tr>
    </table>

    <hr style="border:none; border-top:1px solid #d1d5db;">

    <div style="white-space: pre-wrap; margin-top: 12px;">{{ $bodyText }}</div>
</div>
