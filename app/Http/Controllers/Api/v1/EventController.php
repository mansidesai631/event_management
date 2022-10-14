<?php

namespace App\Http\Controllers\Api\v1;

use App\Event;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventCollection;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Validator;
use DB;

class EventController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->all();
        $validator = Validator::make($validatedData, [
            'event_name' => 'required|string',
            'description' => 'required|string',
            'address' => 'required|string',
            'date' => 'required|date_format:Y-m-d'
        ]);

        if ($validator->fails()) {
            $messages = $validator->messages();

            $content = array(
                'success' => false,
                'data'    => null,
                'message' => $messages,
                'href' =>  $request->path()
            );

            return response()->json($content)->setStatusCode(400);
        }

        $event = Event::create([
            'event_name' => $request->input('event_name'),
            'description' => $request->input('description'),
            'address' => $request->input('address'),
            'date' => $request->input('date'),
            'user_id' => $request->user()->id
        ]);

        return new EventResource($event);
    }

    public function update(Event $event, Request $request)
    {
        $this->authorize('update', $event);
        $validatedData = $request->validate([
            'event_name' => 'nullable|string',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'date' => 'nullable|date_format:Y-m-d\TH:i:sP'
        ]);

        $event->event_name = array_get($validatedData, 'event_name', $event->event_name);
        $event->description = array_get($validatedData, 'description', $event->description);
        $event->address = array_get($validatedData, 'address', $event->address);
        $event->date = array_get($validatedData, 'date', $event->date);
        // Save it!
        $event->save();

        return new EventResource($event);
    }

    public function index(Request $request)
    {

        $user = Auth::User();

        $events = Event::leftjoin('event_user', 'events.id', '=', 'event_user.event_id')
            ->leftjoin('users', 'event_user.user_id', '=', 'users.id')
            ->where('events.user_id', $user->id)
            ->where('events.deleted_at', NULL)
            ->select(['events.event_name', 'events.description', 'events.date', 'users.name']);

        if ($request->has('date')) {
            $events = $events->where('date', '=', $request->date);
        }

        if ($filter = $request->get('search')) {
            $events->where(function ($query) use ($filter) {
                $query->where('events.event_name', 'LIKE', '%'.$filter.'%')
                    ->orWhere('events.description', 'LIKE', '%'.$filter.'%');
            });
        }

        $events = $events->orderBy('event_name', 'DESC')->paginate(($request->per_page) ? (int) $request->per_page : 15);

        return new EventCollection($events);
    }

    public function invite(Event $event, Request $request)
    {

        $validatedData = $request->all();
        $validator = Validator::make($validatedData, [
            'user_ids' => 'required|array',
            'user_ids.*' => 'numeric|exists:users,id',
        ]);

        if ($validator->fails()) {
            $messages = $validator->messages();

            $content = array(
                'success' => false,
                'data'    => null,
                'message' => $messages,
                'href' =>  $request->path()
            );

            return response()->json($content)->setStatusCode(400);
        }

        $event->users()->attach($validatedData['user_ids']);

        $response = [
            'success' => true,
        ];

        return response($response, 201);
    }

    public function show(Event $event, Request $request)
    {
        return new EventResource($event);
    }
}
