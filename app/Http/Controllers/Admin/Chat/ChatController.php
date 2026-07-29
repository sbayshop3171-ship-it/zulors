<?php

namespace App\Http\Controllers\Admin\Chat;

use Throwable;
use App\Models\Chat;
use App\Enums\Chat\ChatType;
use App\Support\Views\Flash;
use Illuminate\Http\Request;
use App\Enums\Flash\FlashType;
use App\Http\Controllers\Controller;
use App\Actions\Chat\DeleteChatAction;
use App\Actions\Chat\DeleteGroupAction;

class ChatController extends Controller
{
    private $filters = [];

    public function index(Request $request)
    {
        $type = $request->string('type')->value;
        $type = in_array($type, ['group', 'direct']) ? $type : 'all';

        $this->filters = [
            'search' => $request->string('search')->value,
            'type' => $type,
        ];

        $chats = Chat::when(($this->filters['type'] != 'all'), function($query) {
            $query->where('type', ChatType::tryFrom($this->filters['type']));
        })->when((! empty($this->filters['search'])), function($query) {
            $query->where(function($query) {
                $query->whereHas('participants', function ($q) {
                    $q->whereHas('user', function ($q) {
                        $q->where('first_name', 'like', "%{$this->filters['search']}%")
                            ->orWhere('last_name', 'like', "%{$this->filters['search']}%")
                            ->orWhere('username', 'like', "%{$this->filters['search']}%");
                    });
                })->orWhereHas('group', function ($q) {
                    $q->where('name', 'like', "%{$this->filters['search']}%")
                        ->orWhere('description', 'like', "%{$this->filters['search']}%");
                })->orWhere('id', $this->filters['search'])
                    ->orWhere('chat_id', $this->filters['search']);
            });
        })->withCount(['participants', 'messages'])->with(['group', 'participants.user' => function ($query) {
            $query->take(2);
        }])->latest('id')->paginate(10);

        return view('admin::chats.index.index', [
            'chats' => $chats,
            'filters' => $this->filters,
        ]);
    }

    public function show(int $chatId)
    {
        $chatData = $this->getChatData($chatId);

        if($chatData->type->isDirect()) {
            $chatData->load('participants.user');
        } else {
            $chatData->load('group');
        }

        return view('admin::chats.show.index', [
            'chatData' => $chatData,
        ]);
    }

    private function getChatData(int $chatId)
    {
        return Chat::withCount(['participants', 'messages'])->findOrFail($chatId);
    }

    public function destroy(int $chatId)
    {
        $chatData = $this->getChatData($chatId);

        try {
            if($chatData->type->isGroup()) {
                (new DeleteGroupAction($chatData->group))->execute();
            }

            (new DeleteChatAction($chatData))->execute();
        } catch (Throwable $e) {
            return redirect()->route('admin.chats.index')->with('flashMessage', (new Flash(content: $e->getMessage(), type: FlashType::ERROR))->get());
        }
        
        return redirect()->route('admin.chats.index')->with('flashMessage', (new Flash(content: __('admin/flash.chat.delete_success')))->get());
    }
}
