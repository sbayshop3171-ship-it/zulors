const profileUsernamePattern = /^[a-zA-Z0-9._]{2,32}$/;

const normalizeProfileUsername = (username) => {
    return String(username || '').trim();
};

const isValidProfileUsername = (username) => {
    return profileUsernamePattern.test(normalizeProfileUsername(username));
};

const makeProfileRoute = (username) => {
    const normalizedUsername = normalizeProfileUsername(username);

    if(! isValidProfileUsername(normalizedUsername)) {
        return {
            name: 'home_index'
        };
    }

    return {
        name: 'profile_posts',
        params: {
            id: normalizedUsername
        }
    };
};

export { normalizeProfileUsername, isValidProfileUsername, makeProfileRoute };
