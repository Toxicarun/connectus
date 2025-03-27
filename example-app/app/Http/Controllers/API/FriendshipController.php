<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Friendship;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;

class FriendshipController extends Controller
{
      // Send Friend Request
      public function sendRequest(Request $request, $receiverId)
      {
          $senderId = Auth::id();
  
          // Check if friendship exists and was rejected
          $existing = Friendship::where(function ($query) use ($senderId, $receiverId) {
              $query->where('sender_id', $senderId)
                  ->where('receiver_id', $receiverId);
          })->orWhere(function ($query) use ($senderId, $receiverId) {
              $query->where('sender_id', $receiverId)
                  ->where('receiver_id', $senderId);
          })->first();
  
          if ($existing) {
              if ($existing->status == 'rejected') {
                  $existing->update(['status' => 'pending']);
                  return response()->json(['message' => 'Friend request re-sent.']);
              } elseif ($existing->status == 'pending') {
                  return response()->json(['message' => 'Friend request already sent.'], 400);
              } elseif ($existing->status == 'accepted') {
                  return response()->json(['message' => 'You are already friends.'], 400);
              }
          }
  
          Friendship::create([
              'sender_id' => $senderId,
              'receiver_id' => $receiverId,
              'status' => 'pending',
          ]);
  
          return response()->json(['message' => 'Friend request sent.']);
      }
  
      // Accept Friend Request
      public function acceptRequest($id)
      {

          $friendship = Friendship::where('id', $id)
              ->where('receiver_id', Auth::id())
              ->where('status', 'pending')
              ->first();
  
          if (!$friendship) {
              return response()->json(['message' => 'Invalid request'], 400);
          }
  
          $friendship->update(['status' => 'accepted']);
          return response()->json(['message' => 'Friend request accepted.']);
      }
  
      // Reject Friend Request
      public function rejectRequest($id)
      {
          $friendship = Friendship::where('id', $id)
              ->where('receiver_id', Auth::id())
              ->where('status', 'pending')
              ->first();
  
          if (!$friendship) {
              return response()->json(['message' => 'Invalid request'], 400);
          }
  
          $friendship->update(['status' => 'rejected']);
          return response()->json(['message' => 'Friend request rejected.']);
      }
  
      // List of Friend Requests Sent
      public function sentRequests()
      {
        $sent = Friendship::where('sender_id', Auth::id())
        ->where('status', 'pending')
        ->with('receiver:id,name') // Load receiver details (ID and Name)
        ->get();

    return response()->json($sent);
      }
  
      // List of Received Friend Requests
      public function receivedRequests()
      {
        $received = Friendship::where('receiver_id', Auth::id())
        ->where('status', 'pending')
        ->with('sender:id,name') // Load sender details (ID and Name)
        ->get();

          return response()->json($received);
      }
  
      // List of Friends
      public function friendsList()
      {
    //       $friends = Friendship::where(function ($query) {
    //     $query->where('sender_id', Auth::id())->orWhere('receiver_id', Auth::id());
    // })
    // ->where('status', 'accepted')
    // ->with(['sender:id,name', 'receiver:id,name']) // Load sender and receiver names
    // ->get();

    $friends = Friendship::where(function ($query) {
        $query->where('sender_id', Auth::id())->orWhere('receiver_id', Auth::id());
    })
    ->where('status', 'accepted')
    ->with(['sender:id,name,latitude,longitude', 'receiver:id,name,latitude,longitude'])
    ->get()
    ->map(function ($friend) {
        return [
            'id' => $friend->id,
            'sender' => $friend->sender,
            'receiver' => $friend->receiver,
            'friend' => $friend->sender_id == Auth::id() ? $friend->receiver : $friend->sender, // The other user
        ];
    });


  
          return response()->json($friends);
      }

      public function unfriend($friendId)
        {
            $userId = Auth::id();

            $friendship = Friendship::where(function ($query) use ($userId, $friendId) {
                $query->where('sender_id', $userId)
                    ->where('receiver_id', $friendId);
            })->orWhere(function ($query) use ($userId, $friendId) {
                $query->where('sender_id', $friendId)
                    ->where('receiver_id', $userId);
            })->whereIn('status', ['accepted', 'pending'])->first();

            if (!$friendship) {
                return response()->json(['message' => 'You are not friends'], 400);
            }

            $friendship->delete();

            return response()->json(['message' => 'Unfriended successfully']);
        }

        public function myprofile()
        {
            $data = User::where('id', Auth::id())->first();
            return response()->json(['message' => 'profile data', 'data'=>$data]);

        }




        public function sendMessage(Request $request)
                {
                    // Validate input
                    $request->validate([
                        'receiver_id' => 'required|exists:users,id',
                        'message' => 'required|string',
                    ]);

                    $sender_id = Auth::id();
                    $receiver_id = $request->receiver_id;

                    // Check if the receiver is a friend with status 'accepted'
                    if (!$this->isFriend($sender_id, $receiver_id)) {
                        return response()->json(['error' => 'You can only send messages to accepted friends.'], 403);
                    }

                    // Save message
                    $message = Message::create([
                        'sender_id' => $sender_id,
                        'receiver_id' => $receiver_id,
                        'message' => $request->message,
                        'status' => 'sent',
                    ]);

                    return response()->json(['message' => 'Message sent successfully!', 'data' => $message], 201);
                }

                // Function to check friendship status
                private function isFriend($sender_id, $receiver_id)
                {
                    return Friendship::where(function ($query) use ($sender_id, $receiver_id) {
                            $query->where('sender_id', $sender_id)
                                ->where('receiver_id', $receiver_id)
                                ->where('status', 'accepted');
                        })
                        ->orWhere(function ($query) use ($sender_id, $receiver_id) {
                            $query->where('sender_id', $receiver_id)
                                ->where('receiver_id', $sender_id)
                                ->where('status', 'accepted');
                        })
                        ->exists();
                }


                public function getMessages(Request $request)
                        {
                            $userId = Auth::id();
                                $otherUserId = request('other_user_id'); // Ensure this is coming from the request

                                $messages = Message::where(function ($query) use ($userId, $otherUserId) {
                                        $query->where('sender_id', $userId)
                                            ->where('receiver_id', $otherUserId);
                                    })
                                    ->orWhere(function ($query) use ($userId, $otherUserId) {
                                        $query->where('sender_id', $otherUserId)
                                            ->where('receiver_id', $userId);
                                    })
                                    ->with(['sender:id,name', 'receiver:id,name'])
                                    ->orderBy('created_at', 'asc') // Messages in chronological order
                                    ->get()
                                    ->map(function ($message) use ($userId) {
                                        return [
                                            'id' => $message->id,
                                            'my_id' => $userId,
                                            'display' => $message->sender_id == $userId ? 'right' : 'left',
                                            'sender_id' => $message->sender_id,
                                            'receiver_id' => $message->receiver_id,
                                            'message' => $message->message,
                                            'from' => $message->sender_id == $userId ? 'me' : 'them', // If Auth user is sender, 'me', else 'them'
                                            'from_id' => $message->sender_id, 
                                            'them_id' => $message->receiver_id, 
                                            'sender' => [
                                                'id' => $message->sender->id,
                                                'name' => $message->sender->name,
                                            ],
                                            'receiver' => [
                                                'id' => $message->receiver->id,
                                                'name' => $message->receiver->name,
                                            ]
                                        ];
                                    });

                                 $message_ststus = Message::where('sender_id',$otherUserId)->update(['status'=>'read']);

                            return response()->json($messages);
                        }


                        public function getChatUsers()
                                    {
                                        $userId = Auth::id();

                                        $users = Message::where('sender_id', $userId)
                                            ->orWhere('receiver_id', $userId)
                                            ->with(['sender:id,name', 'receiver:id,name'])
                                            ->orderBy('created_at', 'desc')
                                            ->get()
                                            ->groupBy(function ($message) use ($userId) {
                                                return $message->sender_id == $userId ? $message->receiver_id : $message->sender_id;
                                            })
                                            ->map(function ($messages, $otherUserId) use ($userId) {
                                                $lastMessage = $messages->first(); // Last message between the two users

                                                // Count unread messages (messages sent by the other user and not read)
                                                $unreadCount = $messages->where('receiver_id', $userId)
                                                                        ->where('status', 'sent') // Assuming "sent" means unread
                                                                        ->count();

                                                // Fetch user details
                                                $otherUser = $lastMessage->sender_id == $userId ? $lastMessage->receiver : $lastMessage->sender;

                                                return [
                                                    'user_id' => $otherUser->id,
                                                    'name' => $otherUser->name,
                                                    'last_message' => $lastMessage->message,
                                                    'last_message_time' => $lastMessage->created_at->format('Y-m-d H:i:s'),
                                                    'unread_count' => $unreadCount,
                                                ];
                                            })
                                            ->values(); // Reset array indexes

                                        return response()->json($users);
                                    }



}
