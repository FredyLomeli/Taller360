<?php

namespace App\Mail;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class SaleNoteEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $sale;
    protected $pdfOutput;

    // Recibimos la Venta y el PDF en binario (crudo)
    public function __construct(Sale $sale, $pdfOutput)
    {
        $this->sale = $sale;
        $this->pdfOutput = $pdfOutput;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nota de Venta #' . str_pad($this->sale->id, 6, '0', STR_PAD_LEFT),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.sale_note',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            // Adjuntamos el PDF en memoria
            Attachment::fromData(fn () => $this->pdfOutput, 'Nota_Venta_'.$this->sale->id.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
