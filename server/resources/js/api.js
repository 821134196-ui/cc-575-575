// 轻量 API 客户端：直接调用同源 /api
async function request(method, path, body) {
    const opts = {
        method,
        headers: { 'Accept': 'application/json' },
    };
    if (body instanceof FormData) {
        opts.body = body;
    } else if (body !== undefined) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
    }

    const res = await fetch(`/api${path}`, opts);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const msg = data.message || `请求失败（${res.status}）`;
        const err = new Error(msg);
        err.status = res.status;
        err.errors = data.errors || {};
        err.errorCode = data.error_code;
        throw err;
    }
    return data;
}

export const api = {
    get: (p) => request('GET', p),
    post: (p, b) => request('POST', p, b),
};
