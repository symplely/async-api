<?php

/*
 +----------------------------------------------------------------------+
 | PHP Version 7                                                        |
 +----------------------------------------------------------------------+
 | Copyright (c) 1997-2018 The PHP Group                                |
 +----------------------------------------------------------------------+
 | This source file is subject to version 3.01 of the PHP license,      |
 | that is bundled with this package in the file LICENSE, and is        |
 | available through the world-wide-web at the following url:           |
 | http://www.php.net/license/3_01.txt                                  |
 | If you did not receive a copy of the PHP license and are unable to   |
 | obtain it through the world-wide-web, please send a note to          |
 | license@php.net so we can mail you a copy immediately.               |
 +----------------------------------------------------------------------+
 | Authors: Martin Schröder <m.schroeder2007@gmail.com>                 |
 +----------------------------------------------------------------------+
 */

namespace Concurrent
{
    /**
     * Union of all awaitable objects (Deferred and Task) being used for type-hinting.
     */
    interface Awaitable { }
    
    /**
     * A channel is used to transfer messages between async tasks.
     * 
     * Send and receive operations are (async) blocking by default, they can be used
     * to synchronize tasks. Buffered channels allow for non-blocking send as long as
     * there is space for additional messages in the buffer.
     */
    final class Channel implements \IteratorAggregate
    {
        /**
         * Create a new channel.
         * 
         * @param int $capacity Number of messages that can be buffered by the channel.
         */
        public function __construct(int $capacity = 0) { }
        
        /**
         * Get an iterator that can read messages from the channel.
         * 
         * Channel ierators cannot be shared between multiple tasks, each task needs
         * it's own iterator instance!
         */
        public function getIterator(): ChannelIterator { }
        
        /**
         * Close the channel.
         * 
         * @param \Throwable $e If you pass an error the channel will rethrow the error into receive operations.
         */
        public function close(?\Throwable $e = null): void { }
        
        /**
         * Check if the channel has been closed yet.
         */
        public function isClosed(): bool { }
        
        /**
         * Check if a message can be sent without blocking.
         */
        public function isReadyForSend(): bool { }
        
        /**
         * Check if a message can be received without blocking.
         */
        public function isReadyForReceive(): bool { }
        
        /**
         * Send a message into the channel.
         * 
         * This will (async) block if the channel is unbuffered or no space is left in the channel buffer.
         * 
         * @param mixed $message Arbitrary message, can be any value of any type (even another channel object).
         * 
         * @throws ChannelClosedException When attempting to send a message into a closed channel.
         */
        public function send($message): void { }
    }
    
    /**
     * Is thrown when an operation cannot be performed due to a channel being closed.
     */
    final class ChannelClosedException extends \Exception { }
    
    /**
     * Provides methods that operate on multiple channels at once.
     */
    final class ChannelGroup implements \Countable
    {
        /**
         * Create a channel group from the given input channels.
         * 
         * @param array $channels Input channels or channel iterators.
         * @param bool $shuffle Shuffle all inputs before every operation?
         */
        public function __construct(array $channels, bool $shuffle = false) { }
        
        /**
         * Returns the number of non-closed input channels.
         * 
         * Count will only be updatet during a call to select(), be prepared to handle stale values.
         */
        public function count(): int { }
        
        /**
         * Perform a receive operation on all input channels, return the first received message and
         * cancel all other receive operations. Will return NULL if no message could be received.
         * 
         * If no timeout is used the call will (async) block until eighter a message is received or all
         * input channels have been closed.
         * 
         * If a timeout of 0 is used the call will check all input channels for a pending message (buffered
         * message or queued sender) and return it. If no channel is ready for receipt (or all channels are
         * closed) it will return without blocking.
         * 
         * If a timeout is used behavior is the same as with no timeout but the call will also return NULL if
         * the timeout is exceeded (be sure to check count in this case!).
         * 
         * Errors caused by channels closed with an error are rethrown exactly once by select! After that the
         * input channel will be dropped from the channel group.
         * 
         * @param int $timeout The timeout (in milliseconds) to be used (null disables timeout).
         * @return ChannelSelect Selected message or NULL when no channels was ready.
         * 
         * @throws ChannelClosedException If any input channel has been closed with an error.
         */
        public function select(?int $timeout = null): ?ChannelSelect { }
        
        /**
         * Sends the given message into the first channel that is (or becomes) ready for receive and returns the
         * key of the receiving channel (as specified in the input channel array) or NULL when the channel group
         * doe not contain any more channels.
         * 
         * If a timeout of 0 is used the send operation will only be performed if the message can be delivered into
         * a channel buffer or a receiver is waiting to read a message from the channel. If no channel is ready for
         * sent NULL will be returned.
         * 
         * If you specify a timeout send() will return NULL if no channel was ready for receive within the timeout
         * period. If a channel becomes ready for receive everything works the same way as with no timeout.
         * 
         * Every closed channel is reported (by throwing a ChannelClosedException) exactly once and then removed
         * from the channel group.
         * 
         * @param mixed $message Message to be sent.
         * @param int $timeout The timeout (in milliseconds) to be used (null disables timeout).
         * @return mixed Returns the key of the receiving channel (as specified in the input channel array) or NULL.
         * 
         * @throws ChannelClosedException Whenever any of the grouped channels is closed.
         */
        public function send($message, ?int $timeout = null) { }
    }
    
    /**
     * Exposes the result of a select operation.
     */
    final class ChannelSelect
    {
        /**
         * The array key of the channel that provided the value.
         * 
         * @var mixed
         */
        public $key;
        
        /**
         * Value received from the channel.
         * 
         * @var mixed
         */
        public $value;
    }
    
    /**
     * Iterator being used to receive messages from a channel.
     * 
     * The iterator cannot be shared between multiple tasks!
     */
    final class ChannelIterator implements \Iterator
    {
        /**
         * {@inheritdoc}
         */
        public function rewind() { }
        
        /**
         * {@inheritdoc}
         */
        public function valid() { }
        
        /**
         * {@inheritdoc}
         */
        public function current() { }
        
        /**
         * {@inheritdoc}
         */
        public function key() { }
    
        /**
         * {@inheritdoc}
         */
        public function next() { }
    }

    /**
     * Provides access to the logical execution context.
     */
    final class Context
    {
        /**
         * Context cannot be created in userland.
         */
        private function __construct() { }

        /**
         * Check if the context has been cancelled yet.
         */
        public function isCancelled(): bool { }
        
        /**
         * Derives a new context with a value bound to the given context var.
         */
        public function with(ContextVar $var, $value): Context { }
        
        /**
         * Derives a new context that allows for contextual us of PHP's output buffering mechanics.
         */
        public function withIsolatedOutput(): Context { }

        /**
         * Derive a new context that will be cancelled after the given number of milliseconds have passed.
         */
        public function withTimeout(int $milliseconds): Context { }
        
        /**
         * Derive a new context and a cancellation handler that can cancel it.
         * 
         * @param mixed $cancel A reference(!) to the variable that receives the cancellation handler.
         */
        public function withCancel(& $cancel): Context { }

        /**
         * Create a context that is shielded from cancellation.
         */
        public function shield(): Context { }

        /**
         * Rethrow the cancellation error into the calling code (does nothing if the context
         * has not been cancelled).
         */
        public function throwIfCancelled(): void { }
        
        /**
         * Enables the context for the duration of the callback invocation, returns the
         * value returned from the callback.
         * 
         * Note: It is safe to use await in the callback.
         */
        public function run(callable $callback, ...$args) { }

        /**
         * Lookup the current logical execution context.
         */
        public static function current(): Context { }
        
        /**
         * Get the background execution context.
         */
        public static function background(): Context { }
    }

    /**
     * Contextual variable being used to bind and access variables in a context.
     */
    final class ContextVar
    {
        /**
         * Retrieve the value bound to the variable from the given context, will
         * return NULL if no value is set for the variable.
         * 
         * Note: Uses the current context (Context::current()) if no context is given.
         */
        public function get(?Context $context = null) { }
    }
    
    /**
     * Is thrown when a context has been cancelled.
     */
    final class CancellationException extends \Exception { }

    /**
     * Provides access to a context that can be cancelled using the handler.
     */
    final class CancellationHandler
    {
        /**
         * Cancel the associated context, the given error will be set as previous error for the cancellation exception.
         */
        public function __invoke(?\Throwable $e = null): void { }
    }

    /**
     * A deferred represents an async operation that may not have completed yet.
     * 
     * It exposes an awaitable to be consumed by other components and provides an API
     * to resolve or fail the awaitable at any time.
     */
    final class Deferred
    {
        /**
         * Current status of the deferred, one of PENDING, RESOLVED or FAILED.
         * 
         * @var string
         */
        public $status;
        
        /**
         * File that contained the deferred construction (can be NULL).
         * 
         * @var string
         */
        public $file;
        
        /**
         * Line where the deferred was created (can be NULL).
         * 
         * @var int
         */
        public $line;
        
        /**
         * Is used to enable support for cancellation. The callback will receive the deferred object
         * and the cancellation error as arguments.
         */
        public function __construct(callable $cancel = null) { }

        /**
         * Provides an awaitable object that can be resolved or failed by the deferred.
         */
        public function awaitable(): Awaitable { }

        /**
         * Resolves the deferred with the given value if it has not been resolved yet.
         */
        public function resolve($val = null): void { }

        /**
         * Fails the deferred with the given error if it has not been resolved yet.
         */
        public function fail(\Throwable $e): void { }

        /**
         * Creates resolved awaitable from the given value.
         */
        public static function value($val = null): Awaitable { }

        /**
         * Creates a failed awaitable from the given error.
         */
        public static function error(\Throwable $e): Awaitable { }

        /**
         * Combines multiple (at least one) awaitables into a single awaitable. Input must be an array of
         * awaitable objects. The continuation is callled with five arguments:
         * 
         * <ul>
         *  <li><b>Deferred</b> - Controls the result awaitable, must be resolved or failed from the callback.</li>
         *  <li><b>bool</b> - Is true when this is the last call to the callback.</li>
         *  <li><b>mixed</b> - Holds the key of the processed awaitable in the input array.</li>
         *  <li><b>?Throwable</b> - Error if the awaitable failed, NULL otherwise.</li>
         *  <li><b>?mixed</b> - Result provided by the awaitable, NULL otherwise.</li>
         * <ul>
         */
        public static function combine(array $awaitables, callable $continuation): Awaitable { }

        /**
         * Applies a transformation callback to the outcome of the awaited operation.
         * 
         * The callback receives a Throwable as first argument if the awaited operation failed. It will receive NULL
         * as first argument and the result value as second argument of the operation was successful.
         * 
         * Returning a value from the callback will resolve the result awaitable with the returned value. Throwing an
         * error from the callback will fail the result awaitable with the thrown error.
         */
        public static function transform(Awaitable $awaitable, callable $transform): Awaitable { }
    }

    /**
     * A task is a fiber-based, concurrent VM execution, that can be paused and resumed.
     */
    final class Task implements Awaitable
    {
        /**
         * Status of the task, one of PENDING, RESOLVED or FAILED.
         * 
         * @var string
         */
        public $status;
        
        /**
         * Name of the file where the task has been created (can be NULL).
         * 
         * @var string
         */
        public $file;
        
        /**
         * Line number within the file where the task has been created (can be NULL).
         *
         * @var string
         */
        public $line;
        
        /**
         * Task cannot be created in userland.
         */
        private function __construct() { }

        /**
         * Run the given callback in an async task or custom awaitable.
         */
        public static function async(callable $callback, ...$args): Awaitable { }

        /**
         * Run the given callback in an async task or custom awaitable within the given context.
         */
        public static function asyncWithContext(Context $context, callable $callback, ...$args): Awaitable { }

        /**
         * Awaits the resolution of the given awaitable.
         * 
         * The current task will be suspended until the input awaitable resolves or is failed.
         * 
         * @throws \Throwable Depends on the awaited operation.
         */
        public static function await(Awaitable $awaitable) { }
    }

    /**
     * Provides scheduling and execution of async tasks.
     */
    final class TaskScheduler
    {
        /**
         * Task scheduler cannot be created in userland.
         */
        private function __construct() { }

        /**
         * Runs the given callback as a task in an isolated scheduler and returns the result.
         * 
         * The inspect callback will be called after the callback-based task completes. It will receive an array
         * of arrays containing information about every unfinished task.
         */
        public static function run(callable $callback, ?callable $inspect = null) { }

        /**
         * Runs the given callback as a task in the given context in an isolated scheduler and returns the result.
         *
         * The inspect callback will be called after the callback-based task completes. It will receive an array
         * of arrays containing information about every unfinished task.
         */
        public static function runWithContext(Context $context, callable $callback, ?callable $inspect = null) { }
    }
    
    /**
     * Executes a PHP file in a thread within the same process.
     */
    final class Thread
    {
        /**
         * Create a new thread.
         * 
         * @param string $file The file to be executed by the thread.
         */
        public function __construct(string $file) { }
  
        /**
         * Check if PHP supports threads (requires ZTS and PHP has to be run from the command line).
         */
        public static function isAvailable(): bool { }
        
        /**
         * Check if PHP is executed within a worker thread.
         */
        public static function isWorker(): bool { }
        
        /**
         * Get access to an IPC pipe that can be used to communicate with the PHP process (or thread)
         * that spawned the worker thread.
         */
        public static function connect(): Network\Pipe { }
        
        /**
         * Get an IPC pipe that can be used to communicate with the spawned thread.
         */
        public function getIpc(): Network\Pipe { }
        
        /**
         * Attempt to kill the worker thread (the thread can not be interrupted while
         * running a blocking function call).
         */
        public function kill(): void { }
        
        /**
         * Await completion of the worker thread.
         * 
         * @return int Simulated exit code of the thread (0 for normal exit, 1 on fatal error / exception. 
         */
        public function join(): int { }
    }
    
    /**
     * Watches the local filesystem for changes.
     */
    final class Monitor
    {
        /**
         * Create a monitor that watches a filesystem path for changes.
         * 
         * Recursive mode is only available on Windows!
         * 
         * @param string $path Path to be monitored (file or directory).
         * @param bool $recursive Watch for changes in subdirectories?
         */
        public function __construct(string $path, ?bool $recursive = null) { }
        
        /**
         * Closes the filesystem monitor.
         * 
         * @param \Throwable $e Reason for close.
         */
        public function close(?\Throwable $e = null): void { }
        
        /**
         * Wait for the next monitored filesystem event.
         */
        public function awaitEvent(): MonitorEvent { }
    }
    
    /**
     * Exposes filesystem event details.
     */
    final class MonitorEvent
    {
        /**
         * Item was created or (re)moved.
         * 
         * @var int
         */
        public const RENAMED = 1;
        
        /**
         * Item was modified.
         * 
         * @var int
         */
        public const CHANGED = 2;
        
        /**
         * Mask of events (check this using event class constants).
         * 
         * @var int
         */
        public $events;
        
        /**
         * Absolute path of the event.
         * 
         * @var string
         */
        public $path;
    }
    
    /**
     * Is thrown when a timeout occurs.
     */
    final class TimeoutException extends \Exception { }

    /**
     * Provides timers and future ticks backed by the internal event loop.
     */
    final class Timer
    {
        /**
         * Create a new timer with the given delay (in milliseconds).
         */
        public function __construct(int $milliseconds) { }

        /**
         * Stops the timer if it is running, this will dispose of all pending await operations.
         * 
         * After a call to this method no further timeout operations will be possible.
         */
        public function close(?\Throwable $e = null): void { }

        /**
         * Suspends the current task until the timer fires.
         */
        public function awaitTimeout(): void { }
        
        /**
         * Creates an awaitable that fails with a TimeoutException after the given number of milliseconds.
         */
        public static function timeout(int $milliseconds): Awaitable { }
    }

    /**
     * Provides non-blocking IO integration.
     */
    final class Poll
    {
        /**
         * Create a poll for the given resource.
         * 
         * @param resource $resource PHP stream or socket resource.
         */
        public function __construct($resource) { }

        /**
         * Close the watcher, this will throw an error into all tasks waiting for readablility / writability.
         * 
         * After a call to this method not further read / write operations can be awaited.
         * 
         * @param \Throwable $e Optional reason that caused closing the watcher.
         */
        public function close(?\Throwable $e = null): void { }

        /**
         * Suspends the current task until the watched resource is reported as readable.
         */
        public function awaitReadable(): void { }

        /**
         * Suspends the current task until the watched resource is reported as writable.
         */
        public function awaitWritable(): void { }
    }

    /**
     * Provides UNIX signal and Windows CTRL + C handling.
     */
    final class Signal
    {
        /**
         * Console window has been closed.
         */
        public const SIGHUP = 1;

        /**
         * Received CTRL + C keyboard interrupt.
         */
        public const SIGINT = 2;

        public const SIGQUIT = 3;

        public const SIGKILL = 9;

        public const SIGTERM = 15;

        public const SIGUSR1 = 10;

        public const SIGUSR2 = 12;

        /**
         * Create a watcher for the given signal number.
         */
        public function __construct(int $signum) { }

        /**
         * Close the watcher, this will throw an error into all tasks waiting for the signal.
         * 
         * After a call to this method not further signals can be awaited using this watcher.
         * 
         * @param \Throwable $e Optional reason that caused closing the watcher.
         */
        public function close(?\Throwable $e = null): void { }

        /**
         * Suspend the current task until the signal is caught.
         */
        public function awaitSignal(): void { }

        /**
         * Check handling the given signal is supported by the OS.
         */
        public static function isSupported(int $signum): bool { }
        
        /**
         * Send a signal to another process.
         * 
         * @param int $pid Process ID of the target process.
         * @param int $signum Signal to be sent (use Signal class constants).
         */
        public static function signal(int $pid, int $signum): void { }
    }
}

namespace Concurrent\Sync
{
    /**
     * Conditions allow tasks to wait until they are signalled.
     */
    final class Condition
    {
        /**
         * Close the condition, throws an error into all waiting tasks.
         *
         * @param \Throwable $e Reason for close.
         */
        public function close(?\Throwable $e = null): void { }
        
        /**
         * Have the calling task wait until it is signalled.
         */
        public function wait(): void { }
        
        /**
         * Signal the next waiting task (has no effect if no tasks are waiting).
         *
         * @return int Number of signalled tasks (0 or 1).
         */
        public function signal(): int { }
        
        /**
         * Signals all tasks that are waiting at the start of this method call.
         *
         * @return int Number of tasks that have been signalled.
         */
        public function broadcast(): int { }
    }
}

namespace Concurrent\Stream
{
    /**
     * Provides read access to a stream of bytes.
     */
    interface ReadableStream
    {
        /**
         * Close the stream, will fail all pending and future reads.
         * 
         * @param \Throwable $e Reason for close.
         */
        public function close(?\Throwable $e = null): void;

        /**
         * Read a chunk of data from the stream.
         * 
         * @param int $length Maximum number of bytes to be read (might return fewer bytes).
         * @return string|NULL Next chunk of data or null if EOF is reached.
         * 
         * @throws StreamClosedException When the stream has been closed before or during the read operation.
         * @throws PendingReadException When another read has not completed yet.
         */
        public function read(?int $length = null): ?string;
    }

    /**
     * Provides write access to a stream of bytes.
     */
    interface WritableStream
    {
        /**
         * Close the stream, will fail all pending and future writes.
         * 
         * @param \Throwable $e Reason for close.
         */
        public function close(?\Throwable $e = null): void;

        /**
         * Write a chunk of data to the stream.
         *
         * @throws StreamClosedException When the stream has been closed before or during the write operation.
         */
        public function write(string $data): void;
    }
    
    /**
     * Union of read and write stream.
     */
    interface DuplexStream extends ReadableStream, WritableStream
    {
        /**
         * Get a readable stream backed by the duplex stream.
         */
        public function getReadableStream(): ReadableStream;
        
        /**
         * Get a writable stream backed by the duplex stream.
         */
        public function getWritableStream(): WritableStream;
    }
    
    /**
     * Provides non-blocking access to STDIN of the PHP process.
     */
    final class ReadablePipe implements ReadableStream
    {
        /**
         * Get an STDIN stream.
         */
        public static function getStdin(): ReadablePipe { }

        /**
         * Check if the stream is a TTY (text terminal).
         */
        public function isTerminal(): bool { }

        /**
         * {@inheritdoc}
         */
        public function read(?int $length = null): ?string { }

        /**
         * {@inheritdoc}
         */
        public function close(?\Throwable $e = null): void { }
    }
    
    /**
     * Provides non-blocking access to the output pipes of the PHP process.
     */
    final class WritablePipe implements WritableStream
    {
        /**
         * Get an STDOUT stream.
         */
        public static function getStdout(): WritablePipe { }
        
        /**
         * Get an STDERR stream.
         */
        public static function getStderr(): WritablePipe { }
        
        /**
         * Check if the stream is a TTY (text terminal).
         */
        public function isTerminal(): bool { }
        
        /**
         * {@inheritdoc}
         */
        public function close(?\Throwable $e = null): void { }
    
        /**
         * {@inheritdoc}
         */
        public function write(string $data): void { }
    }
    
    /**
     * Is thrown due to an error during stream processing.
     */
    class StreamException extends \Exception { }
    
    /**
     * Is thrown when an operation is not allowed due to the stream being closed.
     */
    class StreamClosedException extends StreamException { }
    
    /**
     * Is thrown when an attempt is made to read from a stream while another read is pending.
     */
    class PendingReadException extends StreamException { }
}

namespace Concurrent\Network
{
    use Concurrent\Stream\DuplexStream;
    use Concurrent\Stream\ReadableStream;
    use Concurrent\Stream\StreamException;
    use Concurrent\Stream\WritableStream;
    
    /**
     * Is thrown when a socket-related error is encountered.
     */
    class SocketException extends StreamException { }
    
    /**
     * Is thrown when a socket server fails to accept a new connection.
     */
    class SocketAcceptException extends SocketException { }
    
    /**
     * Is thrown when a socket server cannot bind to an address.
     */
    class SocketBindException extends SocketException { }
    
    /**
     * Is thrown when a socket connection attempt fails.
     */
    class SocketConnectException extends SocketException { }
    
    /**
     * Is thrown when a socket operation fails due to a disconnect.
     */
    class SocketDisconnectException extends SocketException { }
    
    /**
     * Is thrown when a socket server fails to listen on a socket.
     */
    class SocketListenException extends SocketException { }
    
    /**
     * Base contract for all sockets.
     */
    interface Socket
    {
        /**
         * Close the underlying socket.
         * 
         * @param \Throwable $e Reason for close, will be set as previous error.
         */
        public function close(?\Throwable $e = null): void;

        /**
         * Get the local address of the socket.
         */
        public function getAddress(): string;

        /**
         * Get the local network port, or NULL when no port is being used.
         */
        public function getPort(): ?int;

        /**
         * Change the value of a socket option, options are declared as class constants.
         * 
         * @param int $option Option to be changed.
         * @param mixed $value New value to be set.
         * @return bool Will return false when the option is not supported.
         */
        public function setOption(int $option, $value): bool;
    }

    /**
     * Contract for a reliable socket-based stream.
     */
    interface SocketStream extends Socket, DuplexStream
    {
        /**
         * Get the address of the remote peer.
         */
        public function getRemoteAddress(): string;

        /**
         * Get the network port used by the remote peer (or NULL if not network port is being used).
         */
        public function getRemotePort(): ?int;
        
        /**
         * Check if the stream is still alive (readably & connected).
         * 
         * Socket reads & writes may still fail even if this method returned true.
         */
        public function isAlive(): bool;
        
        /**
         * Synchronize with pending async writes.
         *
         * Should always be called before close() if you are using async writes, failing to do so
         * might cancel these writes before they could be completed.
         */
        public function flush(): void;
        
        /**
         * Get the number of bytes queued for send.
         * 
         * @return int Number of bytes in the socket's send queue.
         */
        public function getWriteQueueSize(): int;
    }

    /**
     * Contract for a server that accepts reliable socket streams.
     */
    interface Server extends Socket
    {
        /**
         * Accept the next inbound socket connection.
         * 
         * @throws SocketListenException
         * @throws SocketAcceptException
         */
        public function accept(): SocketStream;
    }
    
    /**
     * Wraps a UNIX domain socket or a named pipe.
     */
    final class Pipe implements SocketStream
    {
        /**
         * Connect the pipe to a pipe server.
         * 
         * @param string $name Path to a UNIX domain socket or pipe name.
         * @param string $host Pipe server host name (Windows only).
         * @param bool $ipc Shall this pipe be used to transfer file descriptors?
         * 
         * @throws SocketConnectException
         */
        public static function connect(string $name, ?string $host = null, ?bool $ipc = null): Pipe { }
        
        /**
         * Import a connected pipe using the given pipe (requires IPC flag to be set).
         */
        public static function import(Pipe $pipe, ?bool $ipc = null): Pipe { }
        
        /**
         * Create a pair of connected pipes.
         */
        public static function pair(?bool $ipc = null): array { }
        
        /**
         * Export the pipe using the given pipe (requires IPC flag to be set).
         */
        public function export(Pipe $pipe): void { }
        
        /**
         * {@inheritdoc}
         */
        public function close(?\Throwable $e = null): void { }
    
        /**
         * {@inheritdoc}
         */
        public function flush(): void { }
        
        /**
         * {@inheritdoc}
         */
        public function getAddress(): string { }
        
        /**
         * {@inheritdoc}
         */
        public function getPort(): ?int { }
        
        /**
         * {@inheritdoc}
         */
        public function getRemoteAddress(): string { }
        
        /**
         * {@inheritdoc}
         */
        public function getRemotePort(): ?int { }
        
        /**
         * {@inheritdoc}
         */
        public function setOption(int $option, $value): bool { }
    
        /**
         * {@inheritdoc}
         */
        public function isAlive(): bool { }
        
        /**
         * {@inheritdoc}
         */
        public function read(?int $length = null): ?string { }
        
        /**
         * {@inheritdoc}
         */
        public function getReadableStream(): ReadableStream { }
    
        /**
         * {@inheritdoc}
         */
        public function write(string $data): void { }
        
        /**
         * {@inheritdoc}
         */
        public function getWritableStream(): WritableStream { }
        
        /**
         * {@inheritdoc}
         */
        public function getWriteQueueSize(): int { }
    }
    
    /**
     * Wraps a UNIX domain socket or named pipe in server mode.
     */
    final class PipeServer implements Server
    {
        /**
         * Bind a new pipe server but do not start listening for incoming connection yet.
         * 
         * The server will start to listen when accept() is called for the first time.
         * 
         * @param string $name UNIX domain socket path or named piep anme.
         * @param bool $ipc Shall the pipe server accept pipes that can be used to transfer file descriptors?
         * 
         * @throws SocketBindException
         */
        public static function bind(string $name, ?bool $ipc = null): PipeServer { }
        
        /**
         * Create a new pipe server.
         * 
         * @param string $name UNIX domain socket path or named piep anme.
         * @param bool $ipc Shall the pipe server accept pipes that can be used to transfer file descriptors?
         * 
         * @throws SocketBindException
         * @throws SocketListenException
         */
        public static function listen(string $name, ?bool $ipc = null): PipeServer { }
        
        /**
         * Import a pipe server using the given pipe.
         */
        public static function import(Pipe $pipe, ?bool $ipc = null): PipeServer { }
        
        /**
         * Export the pipe server using thhe given pipe.
         */
        public function export(Pipe $pipe): void { }
        
        /**
         * {@inheritdoc}
         */
        public function close(?\Throwable $e = null): void { }
        
        /**
         * {@inheritdoc}
         */
        public function getAddress(): string { }
        
        /**
         * {@inheritdoc}
         */
        public function getPort(): ?int { }
        
        /**
         * {@inheritdoc}
         */
        public function setOption(int $option, $value): bool { }
    
        /**
         * {@inheritdoc}
         */
        public function accept(): SocketStream { }
    }
    
    /**
     * TCP socket connection.
     */
    final class TcpSocket implements SocketStream
    {
        /**
         * Disables Nagle's Algorithm when set.
         */
        public const NODELAY = 100;
        
        /**
         * Sets the TCP keep-alive timeout in seconds, 0 to disable keep-alive.
         */
        public const KEEPALIVE = 101;
        
        /**
         * Sockets are created using connect() or TcpServer::accept().
         */
        private function __construct() { }
        
        /**
         * Connect to the given peer (will automatically perform a DNS lookup for host names).
         * 
         * @throws SocketConnectException
         */
        public static function connect(string $host, int $port, ?TlsClientEncryption $tls = null): TcpSocket { }
        
        /**
         * Import a TCP socket that is being sent over a pipe.
         */
        public static function import(Pipe $pipe, ?TlsClientEncryption $tls = null): TcpSocket { }
        
        /**
         * Create a pair of connected TCP sockets.
         */
        public static function pair(): array { }
        
        /**
         * Export the socket using the given pipe.
         */
        public function export(Pipe $pipe): void { }
        
        /**
         * {@inheritdoc}
         */
        public function close(?\Throwable $e = null): void { }
        
        /**
         * {@inheritdoc}
         */
        public function flush(): void { }
        
        /**
         * {@inheritdoc}
         */
        public function getAddress(): string { }
        
        /**
         * {@inheritdoc}
         */
        public function getPort(): ?int { }
        
        /**
         * {@inheritdoc}
         */
        public function setOption(int $option, $value): bool { }
        
        /**
         * {@inheritdoc}
         */
        public function getRemoteAddress(): string { }
        
        /**
         * {@inheritdoc}
         */
        public function getRemotePort(): ?int { }
        
        /**
         * {@inheritdoc}
         */
        public function isAlive(): bool { }
        
        /**
         * Negotiate TLS connection encryption, any further data transfer is encrypted.
         */
        public function encrypt(): TlsInfo { }
        
        /**
         * {@inheritdoc}
         */
        public function read(?int $length = null): ?string { }
        
        /**
         * {@inheritdoc}
         */
        public function getReadableStream(): ReadableStream { }
        
        /**
         * {@inheritdoc}
         */
        public function write(string $data): void { }
        
        /**
         * {@inheritdoc}
         */
        public function getWriteQueueSize(): int { }
        
        /**
         * {@inheritdoc}
         */
        public function getWritableStream(): WritableStream { }
    }
    
    /**
     * TCP socket server.
     */
    final class TcpServer implements Server
    {
        /**
         * Enable / disable simultaneous asynchronous accept requests that are queued by the operating system
         * when listening for new TCP connections.
         */
        public const SIMULTANEOUS_ACCEPTS = 150;
        
        /**
         * Servers are created using listen().
         */
        private function __construct() { }
        
        /**
         * Create and bind a TCP server but do not listen for incoming connections yet.
         * 
         * The server will start to listen when accept() is called for the first time.
         * 
         * @throws SocketBindException
         */
        public static function bind(string $host, int $port, ?TlsServerEncryption $tls = null, ?bool $reuseport = null): TcpServer { }
        
        /**
         * Create a TCP server listening on the given interface and port.
         * 
         * @throws SocketBindException
         * @throws SocketListenException
         */
        public static function listen(string $host, int $port, ?TlsServerEncryption $tls = null, ?bool $reuseport = null): TcpServer { }
        
        /**
         * Import a TCP socket server using the given pipe.
         */
        public static function import(Pipe $pipe, ?TlsServerEncryption $tls = null): TcpServer { }
        
        /**
         * Export the TCP server using the given pipe.
         */
        public function export(Pipe $pipe): void { }
        
        /**
         * {@inheritdoc}
         */
        public function close(?\Throwable $e = null): void { }
        
        /**
         * {@inheritdoc}
         */
        public function getAddress(): string { }
        
        /**
         * {@inheritdoc}
         */
        public function getPort(): ?int { }
        
        /**
         * {@inheritdoc}
         */
        public function setOption(int $option, $value): bool { }
        
        /**
         * {@inheritdoc}
         */
        public function accept(): SocketStream { }
    }
    
    /**
     * Socket client encryption settings.
     */
    final class TlsClientEncryption
    {
        /**
         * Allow connecting to hosts that have a self-signed X509 certificate.
         */
        public function withAllowSelfSigned(bool $allow): TlsClientEncryption { }
        
        /**
         * Restrict the maximum certificate validation chain to the given length.
         */
        public function withVerifyDepth(int $depth): TlsClientEncryption { }
        
        /**
         * Set peer name to connect to.
         */
        public function withPeerName(string $name): TlsClientEncryption { }
        
        /**
         * Set list of acceptable ALPN protocol names.
         */
        public function withAlpnProtocols(string ...$protocols): TlsClientEncryption { }
        
        /**
         * Provide the path of a CA dir to be used.
         */
        public function withCertificateAuthorityPath(string $path): TlsClientEncryption { }
        
        /**
         * Provide the CA file to be used.
         */
        public function withCertificateAuthorityFile(string $file): TlsClientEncryption { }
    }
    
    /**
     * Provides information about a negotiated TLS session.
     */
    final class TlsInfo
    {
        /**
         * TLS protocol version.
         * 
         * @var string
         */
        public $protocol;
        
        /**
         * Cipher being used to encrypt data.
         * 
         * @var string
         */
        public $cipher_name;
        
        /**
         * Cipher bits being used to encrypt data.
         * 
         * @var int
         */
        public $cipher_bits;
        
        /**
         * Negotiated ALPN protocol name (can be NULL).
         * 
         * @var string
         */
        public $alpn_protocol;
    }
    
    /**
     * Socket server encryption settings.
     */
    final class TlsServerEncryption
    {
        /**
         * Configure the default X509 certificate to be used by the server.
         * 
         * @param string $cert Path to the certificate file.
         * @param string $key Path to the secret key file.
         * @param string $passphrase Passphrase being used to access the secret key.
         */
        public function withDefaultCertificate(string $cert, string $key, ?string $passphrase = null): TlsServerEncryption { }
        
        /**
         * Configure a host-based X509 certificate to be used by the server.
         * 
         * @param string $host Hostname.
         * @param string $cert Path to the certificate file.
         * @param string $key Path to the secret key file.
         * @param string $passphrase Passphrase being used to access the secret key.
         */
        public function withCertificate(string $host, string $cert, string $key, ?string $passphrase = null): TlsServerEncryption { }
        
        /**
         * Set list of available ALPN protocol names.
         */
        public function withAlpnProtocols(string ...$protocols): TlsServerEncryption { }
        
        /**
         * Provide the path of a CA dir to be used.
         */
        public function withCertificateAuthorityPath(string $path): TlsServerEncryption { }
        
        /**
         * Provide the CA file to be used.
         */
        public function withCertificateAuthorityFile(string $file): TlsServerEncryption { }
    }
    
    /**
     * UDP socket API.
     */
    final class UdpSocket implements Socket
    {
        /**
         * Sets the maximum number of packet forwarding operations performed by routers.
         */
        public const TTL = 200;

        /**
         * Set to true to have multicast packets loop back to local sockets.
         */
        public const MULTICAST_LOOP = 250;

        /**
         * Sets the maximum number of packet forwarding operations performed by routers for multicast packets.
         */
        public const MULTICAST_TTL = 251;
        
        /**
         * Bind a UDP socket to the given local peer.
         * 
         * @param string $address Local network interface address (IP) to be used.
         * @param int $port Local port to be used.
         * 
         * @throws SocketBindException
         */
        public static function bind(string $address, int $port): UdpSocket { }
        
        /**
         * Bind a UDP socket and join the given UDP multicast group.
         * 
         * @param string $group Address (IP) of the UDP multicast group.
         * @param int $port Port being used by the UDP multicast group.
         * 
         * @throws SocketBindException
         */
        public static function multicast(string $group, int $port): UdpSocket { }
        
        /**
         * {@inheritdoc}
         */
        public function close(?\Throwable $e = null): void { }
        
        /**
         * Synchronizes with pending async send operations.
         * 
         * You should always call this method before close() if you are working with async sends, failing to
         * do so might cancel pending send operations before they could be completed.
         */
        public function flush(): void { }
        
        /**
         * {@inheritdoc}
         */
        public function getAddress(): string { }
        
        /**
         * {@inheritdoc}
         */
        public function getPort(): ?int { }
        
        /**
         * {@inheritdoc}
         */
        public function setOption(int $option, $value): bool { }
        
        /**
         * Receive the next UDP datagram from the socket.
         */
        public function receive(): UdpDatagram { }

        /**
         * Transmit the given UDP datagram over the network.
         * 
         * @param UdpDatagram $datagram UDP datagram with payload and remote peer address.
         */
        public function send(UdpDatagram $datagram): void { }
    }
    
    /**
     * Wrapper for a UDP datagram.
     */
    final class UdpDatagram
    {
        /**
         * Transmitted data payload.
         * 
         * @var string
         */
        public $data;
        
        /**
         * IP address of the remote peer.
         * 
         * @var string
         */
        public $address;
        
        /**
         * Port being used by the remote peer.
         * 
         * @var int
         */
        public $port;
        
        /**
         * Create a new UDP datagram.
         * 
         * @param string $data Payload to be transmitted.
         * @param string $address IP address of the remote peer.
         * @param int $port Port being used by the remote peer.
         */
        public function __construct(string $data, string $address, int $port) { }
        
        /**
         * Create a UDP datagram with the same remote peer.
         * 
         * @param string $data Data to be transmitted.
         */
        public function withData(string $data): UdpDatagram { }

        /**
         * Create a datagram with the same transmitted data.
         * 
         * @param string $address IP address of the remote peer.
         * @param int $port Port being used by the remote peer.
         */
        public function withPeer(string $address, int $port): UdpDatagram { }
    }
}

namespace Concurrent\Process
{
    use Concurrent\Network\Pipe;
    use Concurrent\Stream\ReadableStream;
    use Concurrent\Stream\WritableStream;
                                
    /**
     * Provides a unified way to spawn a process.
     */
    final class ProcessBuilder
    {
        /**
         * File descriptor of STDIN.
         */
        public const STDIN = 0;

        /**
         * File descriptor of STDOUT.
         */
        public const STDOUT = 1;

        /**
         * File descriptor of STDERR.
         */
        public const STDERR = 2;

        /**
         * Create a new process configuration.
         * 
         * @param string $command Name of the command to be executed.
         * @param string ...$args Additional arguments / flags to be passed to the command.
         */
        public function __construct(string $command, string ...$args) { }
        
        /**
         * Create a new PHP process.
         * 
         * @param string $file PHP file to be executed by the child process.
         */
        public static function fork(string $file): ProcessBuilder { }
        
        /**
         * Create a system shell process.
         * 
         * @param bool $interactive Launch shell in interactive mode?
         */
        public static function shell(?bool $interactive = false): ProcessBuilder { }
        
        /**
         * Set the work directory of the spawned process.
         */
        public function withCwd(string $directory): ProcessBuilder { }

        /**
         * Set environment variables to be passed to the spawned process.
         * 
         * @param array $env Associative array of env vars to set in the spawned process.
         * @param bool $inherit Inherit all env vars from the parent process.
         */
        public function withEnv(array $env, ?bool $inherit = null): ProcessBuilder { }

        /**
         * Use an async pipe as STDIN.
         */
        public function withStdinPipe(): ProcessBuilder { }
        
        /**
         * Inherit STDIN from the current process.
         * 
         * @param int $fd Can only be 0 = STDIN.
         */
        public function withStdinInherited(?int $fd = null): ProcessBuilder { }
        
        /**
         * Do not use STDIN.
         */
        public function withoutStdin(): ProcessBuilder { }

        /**
         * Use an async pipe as STDOUT.
         */
        public function withStdoutPipe(): ProcessBuilder { }
        
        /**
         * Inherit STDOUT from the current process.
         * 
         * @param int $fd Must be one of 1 = STDOUT or 2 = STDERR.
         */
        public function withStdoutInherited(?int $fd = null): ProcessBuilder { }
        
        /**
         * Do not use STDOUT.
         */
        public function withoutStdout(): ProcessBuilder { }
        
        /**
         * Use an async pipe as STDERR.
         */
        public function withStderrPipe(): ProcessBuilder { }
        
        /**
         * Inherit STDERR from the current process.
         *
         * @param int $fd Must be one of 1 = STDOUT or 2 = STDERR.
         */
        public function withStderrInherited(?int $fd = null): ProcessBuilder { }
        
        /**
         * Do not use STDERR.
         */
        public function withoutStderr(): ProcessBuilder { }
        
        /**
         * Create the process and await termination.
         * 
         * @param string ...$args Additional arguments to be passed to the process.
         * @return int Exit code returned by the process.
         */
        public function execute(string ...$args): int { }

        /**
         * Spawn the process and return a process object.
         * 
         * @param string ...$args Additional arguments to be passed to the process.
         */
        public function start(string ...$args): Process { }
    }
    
    /**
     * Provides access to a started process.
     */
    final class Process
    {
        /**
         * Proccesses are created using ProcessBuilder::start().
         */
        private function __construct() { }
        
        /**
         * Check if the process has been created by the process builder fork() method.
         */
        public static function isWorker(): bool { }
        
        /**
         * Establish IPC pipe with the parent process.
         */
        public static function connect(): Pipe { }
        
        /**
         * Check if the process has terminated yet.
         */
        public function isRunning(): bool { }

        /**
         * Get the identifier of the spawned process.
         */
        public function getPid(): int { }

        /**
         * Get the STDIN pipe stream.
         */
        public function getStdin(): WritableStream { }

        /**
         * Get the STDOUT pipe stream.
         */
        public function getStdout(): ReadableStream { }

        /**
         * Get the STDERR pipe stream.
         */
        public function getStderr(): ReadableStream { }

        /**
         * Get IPC pipe (requires use of process builders fork() method).
         */
        public function getIpc(): Pipe { }
        
        /**
         * Send the given signal to the process.
         * 
         * @param int $signum Signal to be sent (use Signal class constants to avoid magic numbers).
         */
        public function signal(int $signum): void { }

        /**
         * Await termination of the process.
         * 
         * @return int Exit code returned by the process.
         */
        public function join(): int { }
    }
}
