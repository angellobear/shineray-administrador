<?php

namespace App\Notifications;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reset/invitación de contraseña del cliente del storefront. El enlace apunta
 * al frontend Next.js, no al backend (reemplaza `customer.password_reset`).
 */
class CustomerResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(Customer $notifiable): MailMessage
    {
        $url = rtrim((string) config('shineray.storefront_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);

        $minutes = (int) config('auth.passwords.customers.expire', 60);

        return (new MailMessage)
            ->subject('Restablece tu contraseña de Shineray Repuestos')
            ->greeting('Hola '.($notifiable->first_name ?: ''))
            ->line('Recibimos una solicitud para establecer la contraseña de tu cuenta.')
            ->action('Establecer contraseña', $url)
            ->line("Este enlace vence en {$minutes} minutos.")
            ->line('Si no solicitaste esto, ignora este correo.');
    }
}
