from unicircle_ml.features import FEATURE_NAMES
from unicircle_ml.simulate import WorldConfig, generate, without


def small(seed: int = 1, **kwargs) -> WorldConfig:
    return WorldConfig(seed=seed, students=300, days=150, **kwargs)


def test_same_seed_same_world():
    assert generate(small(3)).equals(generate(small(3)))


def test_every_row_has_features_and_a_label():
    df = generate(small())
    assert set(FEATURE_NAMES) <= set(df.columns)
    assert set(df["defaulted"].unique()) <= {0, 1}
    assert df[FEATURE_NAMES].notna().all().all()


def test_bad_actors_are_injected_and_cash_out_badly():
    df = generate(small())
    exits = df[df["loan_kind"] == "exit"]
    assert len(exits) > 0
    assert exits["defaulted"].mean() > df[df["loan_kind"] == "honest"]["defaulted"].mean()


def test_a_type_can_be_removed():
    df = generate(WorldConfig(seed=1, students=300, days=150, mix=without("strategic").mix))
    assert "strategic" not in set(df["student_type"])


def test_the_generator_never_uses_the_trust_rules_to_decide_defaults():
    # Defaults are decided in _make_loan from hidden values only; the rules are imported
    # solely to record the baseline score afterwards.
    import inspect

    from unicircle_ml import simulate

    source = inspect.getsource(simulate.World.run) + inspect.getsource(simulate.World._make_loan)
    assert "rules." not in source
