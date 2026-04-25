const wait = (ms) => {
    return new Promise((resolve) => {
        window.setTimeout(resolve, Math.max(0, ms));
    });
};

export const enforceMinimumDelay = async (startedAt, minimumMs = 400) => {
    const elapsed = performance.now() - startedAt;

    if (elapsed >= minimumMs) {
        return;
    }

    await wait(minimumMs - elapsed);
};

export const withMinimumDelay = async (task, minimumMs = 400) => {
    const startedAt = performance.now();

    const result = await task();
    await enforceMinimumDelay(startedAt, minimumMs);

    return result;
};
