import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';
import vm from 'node:vm';

const sourcePath = path.resolve(process.cwd(), 'amd/src/timezone.js');

const flushPromises = async() => {
    await new Promise((resolve) => setImmediate(resolve));
};

const createDeferred = () => {
    let resolve;
    let reject;

    const promise = new Promise((promiseResolve, promiseReject) => {
        resolve = promiseResolve;
        reject = promiseReject;
    });

    return {promise, resolve, reject};
};

const createSessionStorage = () => {
    const store = new Map();

    return {
        getItem(key) {
            return store.has(key) ? store.get(key) : null;
        },
        setItem(key, value) {
            store.set(key, String(value));
        },
        removeItem(key) {
            store.delete(key);
        },
    };
};

const loadModule = async({browserTimezone = 'Australia/Sydney'} = {}) => {
    const source = await fs.readFile(sourcePath, 'utf8');
    const transformedSource = source
        .replace("import Ajax from 'core/ajax';", 'const Ajax = __mocks.Ajax;')
        .replace("import Notification from 'core/notification';", 'const Notification = __mocks.Notification;')
        .replace('export const init =', 'const init =')
        .concat('\nmodule.exports = {init};\n');

    const pendingCalls = [];
    const notifications = [];
    const reloads = [];
    const sessionStorage = createSessionStorage();

    const context = {
        __mocks: {
            Ajax: {
                call(requests) {
                    assert.equal(requests.length, 1);
                    const deferred = pendingCalls.shift();

                    if (!deferred) {
                        throw new Error('No deferred Ajax response was queued for this test.');
                    }

                    return [deferred.promise];
                },
            },
            Notification: {
                exception(error) {
                    notifications.push(error);
                },
            },
        },
        Intl: {
            DateTimeFormat() {
                return {
                    resolvedOptions() {
                        return {
                            timeZone: browserTimezone,
                        };
                    },
                };
            },
        },
        module: {exports: {}},
        exports: {},
        window: {
            sessionStorage,
            location: {
                reload() {
                    reloads.push('reload');
                },
            },
        },
    };

    vm.runInNewContext(transformedSource, context, {filename: sourcePath});

    return {
        init: context.module.exports.init,
        notifications,
        pendingCalls,
        reloads,
        sessionStorage,
    };
};

const attemptKey = (userId, currentTimezone = 'Asia/Tehran', browserTimezone = 'Australia/Sydney') => {
    return `local_autobrowsertimezone:${userId}:${currentTimezone}:${browserTimezone}`;
};

const defaultConfig = {
    currentTimezone: 'Asia/Tehran',
    reload: true,
    userid: 7,
};

test('pending first attempt stays non-persistent and duplicate init stays in-memory', async() => {
    const module = await loadModule();
    const firstRequest = createDeferred();
    module.pendingCalls.push(firstRequest);

    module.init(defaultConfig);

    assert.equal(module.sessionStorage.getItem(attemptKey(7)), null);

    module.init(defaultConfig);

    assert.equal(module.pendingCalls.length, 0);

    firstRequest.resolve({
        changed: true,
        timezone: 'Australia/Sydney',
        reason: 'updated',
    });
    await flushPromises();

    assert.equal(module.reloads.length, 1);
    assert.equal(module.sessionStorage.getItem(attemptKey(7)), null);
});

test('pending retry attempt keeps the retry marker until a settled outcome exists', async() => {
    const module = await loadModule();
    const key = attemptKey(7);
    const retryRequest = createDeferred();

    module.sessionStorage.setItem(key, 'retry');
    module.pendingCalls.push(retryRequest);

    module.init(defaultConfig);

    assert.equal(module.sessionStorage.getItem(key), 'retry');

    module.init(defaultConfig);

    assert.equal(module.pendingCalls.length, 0);

    retryRequest.resolve({
        changed: true,
        timezone: 'Australia/Sydney',
        reason: 'updated',
    });
    await flushPromises();

    assert.equal(module.sessionStorage.getItem(key), null);
});

test('first generic rejection permits exactly one later retry', async() => {
    const module = await loadModule();
    const key = attemptKey(7);
    const firstRequest = createDeferred();
    const retryRequest = createDeferred();

    module.pendingCalls.push(firstRequest);
    module.init(defaultConfig);

    firstRequest.reject(new Error('transport failed'));
    await flushPromises();

    assert.equal(module.sessionStorage.getItem(key), 'retry');
    assert.equal(module.notifications.length, 1);

    module.pendingCalls.push(retryRequest);
    module.init(defaultConfig);

    retryRequest.resolve({
        changed: true,
        timezone: 'Australia/Sydney',
        reason: 'updated',
    });
    await flushPromises();

    assert.equal(module.sessionStorage.getItem(key), null);
    assert.equal(module.reloads.length, 1);
});

test('second generic rejection becomes permanently guarded for the session', async() => {
    const module = await loadModule();
    const key = attemptKey(7);
    const firstRequest = createDeferred();
    const retryRequest = createDeferred();

    module.pendingCalls.push(firstRequest);
    module.init(defaultConfig);

    firstRequest.reject(new Error('first transport failure'));
    await flushPromises();

    module.pendingCalls.push(retryRequest);
    module.init(defaultConfig);

    retryRequest.reject(new Error('second transport failure'));
    await flushPromises();

    assert.equal(module.sessionStorage.getItem(key), 'guarded');
    assert.equal(module.notifications.length, 2);

    module.init(defaultConfig);

    assert.equal(module.pendingCalls.length, 0);
});

test('deterministic Moodle rejection becomes permanently guarded', async() => {
    const module = await loadModule();
    const key = attemptKey(7);
    const request = createDeferred();

    module.pendingCalls.push(request);
    module.init(defaultConfig);

    request.reject({
        errorcode: 'invalidparameter',
        message: 'Unsupported timezone',
    });
    await flushPromises();

    assert.equal(module.sessionStorage.getItem(key), 'guarded');
    assert.equal(module.notifications.length, 1);

    module.init(defaultConfig);

    assert.equal(module.pendingCalls.length, 0);
});

test('deterministic changed:false authrejected response stays guarded', async() => {
    const module = await loadModule();
    const key = attemptKey(7);
    const request = createDeferred();

    module.pendingCalls.push(request);
    module.init(defaultConfig);

    request.resolve({
        changed: false,
        timezone: 'Asia/Tehran',
        reason: 'authrejected',
    });
    await flushPromises();

    assert.equal(module.sessionStorage.getItem(key), 'guarded');
    assert.equal(module.reloads.length, 0);

    module.init(defaultConfig);

    assert.equal(module.pendingCalls.length, 0);
});

test('guard state stays isolated per Moodle user id', async() => {
    const module = await loadModule();
    const firstUserRequest = createDeferred();
    const secondUserRequest = createDeferred();

    module.pendingCalls.push(firstUserRequest);
    module.init(defaultConfig);

    firstUserRequest.reject({
        errorcode: 'invalidparameter',
        message: 'Unsupported timezone',
    });
    await flushPromises();

    module.pendingCalls.push(secondUserRequest);
    module.init({
        ...defaultConfig,
        userid: 8,
    });

    assert.equal(module.sessionStorage.getItem(attemptKey(7)), 'guarded');
    assert.equal(module.sessionStorage.getItem(attemptKey(8)), null);

    secondUserRequest.resolve({
        changed: true,
        timezone: 'Australia/Sydney',
        reason: 'updated',
    });
    await flushPromises();

    assert.equal(module.reloads.length, 1);
});
