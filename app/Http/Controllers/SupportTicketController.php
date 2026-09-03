<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupportTicketRequest;
use App\Http\Requests\UpdateSupportTicketStatusRequest;
use App\Mail\SupportTicketCreatedMail;
use App\Models\SupportTicket;
use App\Services\AgencyMailSender;
use App\SupportTicketStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(): View
    {
        $tickets = SupportTicket::query()->visibleTo(request()->user())->with(['agency', 'requester'])->latest()->paginate(20);

        return view('support-tickets.index', compact('tickets'));
    }

    public function create(): View
    {
        return view('support-tickets.create');
    }

    public function store(StoreSupportTicketRequest $request, AgencyMailSender $mailSender): RedirectResponse
    {
        $ticket = SupportTicket::query()->create($request->validated() + ['agency_id' => $request->user()->agency_id, 'user_id' => $request->user()->getKey(), 'status' => SupportTicketStatus::Open]);
        $mailSender->send($ticket->agency_id, new SupportTicketCreatedMail($ticket->load(['agency', 'requester'])));

        return to_route('support-tickets.show', $ticket)->with('success', 'Destek talebiniz alındı.');
    }

    public function show(SupportTicket $supportTicket): View
    {
        Gate::authorize('view', $supportTicket);

        return view('support-tickets.show', ['ticket' => $supportTicket->load(['agency', 'user', 'handler'])]);
    }

    public function update(UpdateSupportTicketStatusRequest $request, SupportTicket $supportTicket): RedirectResponse
    {
        $status = SupportTicketStatus::from($request->validated('status'));
        $supportTicket->update(['status' => $status, 'admin_note' => $request->validated('admin_note'), 'handled_by' => $request->user()->getKey(), 'handled_at' => in_array($status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true) ? now() : null]);

        return back()->with('success', 'Destek talebi güncellendi.');
    }
}
